<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore;

use Goat\Domain\Event\BrokenMessage;
use Goat\Domain\Serializer\MimeTypeConverter;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Implementors, please note that the only operation that needs to be atomic
 * in this whole event store is the append() method: since it creates a new
 * event in the history, position and revision keys need to be computed and
 * this may cause data consistency issues notably unique key conflicts during
 * concurent scenarios.
 *
 * On the other hand considering update and move operations, no matter if you
 * use a SQL backend or any other technology: since the store is append only,
 * position and revision keys have already been computed, data consistency
 * conflicts cannot exists and thus those operation theorically do not require
 * to be made atomic using ACID transactions.
 *
 * @todo
 *   - Improve log contextes, normalize {id}#{rev} notation, create an helper
 *     function to do this.
 *   - Evaluate the need to also updating revision numbers when moving an
 *     event, this will be much more complex for backends to implement but it
 *     will remove ambiguities when two events are at the exact same date in
 *     time and must be reversed.
 *   - Evaluate adding an "order" field on the Event class, which when moved,
 *     then is set to the previous relative event +1 value, so that the order
 *     will always be OK within changing the revision numbers, and without
 *     risking serializations error due to revision key conflicts.
 */
abstract class AbstractEventStore implements EventStore, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private NameMap $nameMap;
    private NamespaceMap $namespaceMap;
    private SerializerInterface $serializer;
    private ?string $serializerFormat = null;

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    final public function setSerializer(SerializerInterface $serializer, ?string $format = null): void
    {
        $this->serializer = $serializer;
        $this->serializerFormat = $format;
    }

    /**
     * {@inheritdoc}
     */
    final public function setNameMap(NameMap $nameMap): void
    {
        $this->nameMap = $nameMap;
    }

    /**
     * {@inheritdoc}
     */
    final public function setNamespaceMap(NamespaceMap $namespaceMap): void
    {
        $this->namespaceMap = $namespaceMap;
    }

    /**
     * Get namespace for aggregate type.
     *
     * @internal
     */
    final public function getNamespace(string $aggregateType): string
    {
        return $this->getNamespaceMap()->getNamespace($aggregateType);
    }

    /**
     * Get or create empty namespace map.
     *
     * @internal
     */
    final public function getNameMap(): NameMap
    {
        return $this->nameMap ?? ($this->nameMap = new DefaultNameMap());
    }

    /**
     * {@inheritdoc}
     */
    public function append(object $message, ?string $name = null): EventBuilder
    {
        $execute = function (DefaultEventBuilder $builder) {
            $nameMap = $this->getNameMap();

            $message = $builder->getMessage();

            $event = Event::create($message);
            $eventName = $builder->getMessageName();

            $aggregateId = $builder->getAggregateId() ?? $event->getAggregateId();
            $aggregateType = $builder->getAggregateType();

            $properties = $builder->getProperties();
            $validAt = $builder->getDate();

            // Compute normalized aggregate type, otherwise the PHP native class
            // or type name would be stored in database, we sure don't want that.
            $aggregateType = $nameMap->getName($aggregateType ?? $event->getAggregateType());

            // Compute normalized event name. If event name was given by the
            // caller normalize it before registering it into the properties
            // array. Otherwise, use the name map directly to guess it.
            if ($eventName) {
                $eventName = $nameMap->getName($eventName);
            } else {
                $eventName = $nameMap->getMessageName($message);
            }

            // Compute necessary common properties. Custom properties from
            // caller might be overriden, the store is authoritative on some
            // of them.
            $properties[Property::CONTENT_TYPE] = $this->getSerializationFormat();
            $properties[Property::MESSAGE_TYPE] = $eventName;

            $callback = \Closure::bind(
                static function (Event $event) use ($aggregateId, $aggregateType, $eventName, $properties, $validAt): Event {
                    $event->aggregateId = $aggregateId;
                    $event->aggregateType = $aggregateType;
                    $event->createdAt = new \DateTimeImmutable();
                    $event->name = $eventName;
                    $event->properties = $properties;
                    $event->validAt = $validAt;
                    return $event;
                },
                null, Event::class
            );

            $logContext = [
                'aggregate_id' => (string)$aggregateId,
                'aggregate_type' => $aggregateType,
                'event' => $event,
                'event_name' => $eventName,
            ];

            try {
                $newEvent = $this->doStore($callback($event));
                $this->logger->debug("Event stored", $logContext);

                return $newEvent;

            } catch (\Throwable $e) {
                $this->logger->critical("Event could not be stored", $logContext + ['exception' => $e]);

                throw $e;
            }
        };

        $builder = new DefaultEventBuilder($execute);
        $builder->message($message);

        if ($name) {
            $builder->name($name);
        }

        return $builder;
    }

    /**
     * {@inheritdoc}
     */
    public function update(Event $event): EventBuilder
    {
        return new DefaultEventBuilder(
            function (EventBuilder $builder) use ($event) {
                $logContext = ['event' => $event];

                try {
                    $newEvent = $this->doUpdate($this->prepareUpdate($event, $builder));
                    $this->logger->debug("Event updated", $logContext);

                    return $newEvent;

                } catch (\Throwable $e) {
                    $this->logger->critical("Event could not be updated", $logContext + ['exception' => $e]);

                    throw $e;
                }
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function moveAfterRevision(Event $event, int $afterRevision): EventBuilder
    {
        $newDate = $this->findDateAfter($event, $afterRevision);

        $builder = $this
            ->update($event)
            ->properties([
                Property::MODIFIED_PREVIOUS_REVISION => (string)$event->getRevision(),
                Property::MODIFIED_PREVIOUS_VALID_AT => $event->validAt()->format(\DateTime::ISO8601),
            ])
        ;

        if ($newDate) {
            $builder->date($newDate);
        }

        return $builder;
    }

    /**
     * {@inheritdoc}
     */
    public function moveAtDate(Event $event, \DateTimeInterface $newDate): EventBuilder
    {
        return $this
            ->update($event)
            ->date($newDate)
            ->property(
                Property::MODIFIED_PREVIOUS_VALID_AT,
                $event->validAt()->format(\DateTime::ISO8601)
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function insertAfter(UuidInterface $aggregateId, int $afterRevision, object $message, ?string $name = null): EventBuilder
    {
        $builder = $this
            ->append($message, $name)
            ->aggregate(null, $aggregateId)
            ->property(Property::MODIFIED_INSERTED, '1')
        ;

        $newDate = $this->findDateAfterForInsert($aggregateId, $afterRevision);

        if ($newDate) {
            $builder->date($newDate);
        }

        return $builder;
    }

    /**
     * {@inheritdoc}
     */
    public function failedWith(Event $event, \Throwable $exception): EventBuilder
    {
        return new DefaultEventBuilder(
            function (EventBuilder $builder) use ($event, $exception) {
                $logContext = ['event' => $event];
                try {
                    $newEvent = $this->doUpdate($this->prepareUpdate($event, $builder, $exception));
                    $this->logger->debug("Event updated", $logContext);
                    return $newEvent;
                } catch (\Throwable $e) {
                    $this->logger->critical("Event could not be updated", $logContext + ['exception' => $e]);
                    throw $e;
                }
            }
        );
    }

    /**
     * Real storage implementation, event is already fully populated to the
     * exception of revision and position. properties can be updated too.
     *
     * This returns an event because the storage may take the liberty of
     * changing internals, such as adding new properties.
     */
    abstract protected function doStore(Event $event): Event;

    /**
     * Real update implementation.
     *
     * You must NOT change other values than properties and error message
     * information, all information that identifies the aggregate or the
     * event MUST remain untouched, or here be dragons.
     *
     * This returns an event because the storage may take the liberty of
     * changing internals, such as adding new properties.
     */
    abstract protected function doUpdate(Event $event): Event;

    /**
     * Real move implementation.
     *
     * You must NOT change other values than the revision or possibly the
     * validity date, although it is supposed to already be up to date.
     *
     * This returns an event because the storage may take the liberty of
     * changing internals, such as adding new properties.
     */
    abstract protected function doMoveAt(Event $event, int $newRevision): Event;

    /**
     * Serialize event data.
     *
     * You may, or may not, use this method.
     */
    final protected function messageToString(Event $event): string
    {
        return $this->getSerializer()->serialize(
            $event->getMessage(),
            MimeTypeConverter::mimetypeToSerializer(
                $this->getSerializationFormat()
            )
        );
    }

    /**
     * Unserialize event data.
     *
     * You may, or may not, use this method.
     */
    final protected function stringToMessage(string $eventName, array $properties, $data)
    {
        $defaultFormat = $this->getSerializationFormat();

        if ('string' === ($properties[Property::CONTENT_TYPE] ?? $defaultFormat)) {
            // @todo denormalize scalar values using PROP_MESSAGE_TYPE
            //   as PHP native type to denormalize.
            return $data;
        }

        return $this->serializer->deserialize(
            \is_resource($data) ? \stream_get_contents($data) : $data,
            $properties[Property::MESSAGE_TYPE] ?? $eventName,
            MimeTypeConverter::mimetypeToSerializer(
                $properties[Property::CONTENT_TYPE] ?? $defaultFormat
            )
        );
    }

    /**
     * Unserialize event data with error control.
     *
     * You may, or may not, use this method.
     */
    final protected function hydrateMessage(?string $aggregateType, ?UuidInterface $aggregateId, string $eventName, array $properties, $data)
    {
        try {
            return $this->stringToMessage($eventName, $properties, $data);
        } catch (\Throwable $e) {
            $this->logger->critical("Message could not be hydrated", ['event_name' => $eventName, 'data' => $data, 'properties' => $properties]);

            // Whenever a class name change in the current application and
            // the in use current serializer cannot map properly the event
            // name to an existing class, exceptions will raise. We do not
            // want anything to break in a so ugly fashion, let's return
            // a broken message and allow the upper layer using us do what
            // it has to do or fail by itself.
            return new BrokenMessage($aggregateType, $aggregateId, $data, $eventName);
        }
    }

    /**
     * Get serialization format.
     */
    final protected function getSerializationFormat(): string
    {
        return $this->serializerFormat ?? Property::DEFAULT_CONTENT_TYPE;
    }

    /**
     * Get namespace for aggregate type.
     */
    final protected function getSerializer(): SerializerInterface
    {
        return $this->serializer;
    }

    /**
     * Prepare update and metadata for update along.
     */
    private function prepareUpdate(Event $event, DefaultEventBuilder $builder, ?\Throwable $exception = null): Event
    {
        $exceptionTraceAsString = null;
        if ($exception) {
            $exceptionTraceAsString = $this->normalizeExceptionTrace($exception);
        }

        $callback = \Closure::bind(
            function () use ($event, $builder, $exception, $exceptionTraceAsString): Event {
                $event = clone $event;

                $properties = $builder->getProperties();
                $properties[Property::MODIFIED_AT] = (new \DateTime())->format(\DateTime::ISO8601);

                $validAt = $builder->getDate();
                if ($validAt) {
                    $properties[Property::MODIFIED_PREVIOUS_VALID_AT] = $event->validAt()->format(\DateTime::ISO8601);
                    $event->validAt = $validAt;
                }

                $name = $builder->getMessageName();
                if ($name) {
                    $originalName = $event->getName();
                    if ($originalName !== $name) {
                        $properties[Property::MODIFIED_PREVIOUS_NAME] = $originalName;
                    }
                    $event->name = $name;
                }

                foreach ($properties as $key => $value) {
                    if (null === $value || '' === $value) {
                        unset($event->properties[$key]);
                    } else {
                        $event->properties[$key] = (string)$value;
                    }
                }

                if ($exception) {
                    $event->hasFailed = true;
                    $event->errorCode = $exception->getCode();
                    $event->errorMessage = $exception->getMessage();
                    $event->errorTrace = $exceptionTraceAsString;
                }

                return $event;
            },
            null, Event::class
        );

        return $callback();
    }

    /**
     * Find suitable date to set to the given event for inserting it after
     * the given revision to re-order the stream.
     *
     * @return null|\DateTimeInterface
     *   If null returned here, this means the event needs not to be moved.
     */
    private function findDateAfter(Event $event, int $afterRevision): ?\DateTimeInterface
    {
        $logContext = [
            'id' => $event->getAggregateId(),
            'rev' => $event->getRevision(),
            'previous' => $afterRevision,
        ];

        if ($event->getRevision() === $afterRevision) {
            $this->logger->notice("findDateAfter({id}#{rev}, #{previous}) - current element already is the target revision", $logContext);

            return null;
        }

        $dateBefore = null;
        $dateAfter = null;
        $eventDate = $event->validAt();

        $bounds = $this
            ->query()
            ->for($event->getAggregateId())
            ->fromRevision($afterRevision)
            ->limit(2)
            ->execute()
        ;

        $previous = $bounds->fetch();

        if ($previous) {
            $this->logger->debug("findDateAfter({id}#{rev}, #{previous}) - found target revision", $logContext);

            $dateBefore = $previous->validAt();

            // We are going to position our updated event between two existing
            // events, we will have to do date magic.
            $next = $bounds->fetch();
            if ($next) {
                if ($next->getRevision() === $event->getRevision()) {
                    // Object is already at the right place, just return.
                    return null;
                }

                $dateAfter = $next->validAt();
            }
        } else {
            $this->logger->warning("findDateAfter({id}#{rev}, #{previous}) - revision does not exist", $logContext);

            // We don't have an event matching the given revision, find the
            // previous one, any one, and use it as date bound.
            $anyPrevious = $this
                ->query()
                ->for($event->getAggregateId())
                ->fromRevision($afterRevision)
                ->reverse(true)
                ->limit(1)
                ->execute()
                ->fetch()
            ;

            if ($anyPrevious) {
                if ($anyPrevious->getRevision() === $event->getRevision()) {
                    $this->logger->debug("findDateAfter({id}#{rev}, #{previous}) - event is already at the right place", $logContext);

                    return null;
                }

                $dateBefore = $anyPrevious->validAt();
            }
        }

        if ($dateBefore) {
            \assert($dateBefore instanceof \DateTimeInterface);

            if ($dateAfter) {
                if ($dateBefore < $eventDate && $eventDate < $dateAfter) {
                    $this->logger->debug("findDateAfter({id}#{rev}, #{previous}) - event is already at the right place");

                    return null;
                }

                return $this->findDateBetween($dateBefore, $dateAfter);
            }

            if ($dateBefore < $eventDate) {
                $this->logger->debug("findDateAfter({id}#{rev}, #{previous}) - event is already at the right place");

                return null;
            }

            return $dateBefore->modify('+1 sec');
        }

        // No bounds means we are creating the stream, and our event does
        // not exist, since it should have been selected at least by one
        // of those queries if it was alone in the stream. Log this error
        // and just run the update.
        $this->logger->warning("findDateAfter({id}#{rev}, #{previous}) - stream is empty");

        return null;
    }

    /**
     * Find suitable date to set to the given event for inserting it after
     * the given revision to re-order the stream.
     *
     * @return null|\DateTimeInterface
     *   If null returned here, this means the event needs not to be moved.
     */
    private function findDateAfterForInsert(UuidInterface $aggregateId, int $afterRevision): ?\DateTimeInterface
    {
        $logContext = ['id' => $aggregateId, 'previous' => $afterRevision];

        $bounds = $this
            ->query()
            ->for($aggregateId)
            ->fromRevision($afterRevision)
            ->limit(2)
            ->execute()
        ;

        $previous = $bounds->fetch();

        if ($previous) {
            $this->logger->debug("findDateAfterForInsert({id}, #{previous}) - found target revision", $logContext);

            // We are going to position our updated event between two existing
            // events, we will have to do date magic.
            $next = $bounds->fetch();
            if ($next) {
                return $this->findDateBetween($previous->validAt(), $next->validAt());
            }

            return $previous->validAt()->modify('+1 sec');
        }

        $this->logger->warning("findDateAfterForInsert({id}, #{previous}) - revision does not exist", $logContext);

        // We don't have an event matching the given revision, find the
        // previous one, any one, and use it as date bound.
        $anyPrevious = $this
            ->query()
            ->for($aggregateId)
            ->fromRevision($afterRevision)
            ->reverse(true)
            ->limit(1)
            ->execute()
            ->fetch()
        ;

        if ($anyPrevious) {
            return $anyPrevious->validAt()->modify('+1 sec');
        }

        // No bounds means we are creating the stream, and our event does
        // not exist, since it should have been selected at least by one
        // of those queries if it was alone in the stream. Log this error
        // and just run the update.
        $this->logger->warning("findDateAfter({id}, #{previous}) - stream is empty");

        return null;
    }

    /**
     * I am not proud of this one, but it'll work.
     */
    private function findDateBetween(\DateTimeInterface $from, \DateTimeInterface $to): \DateTimeInterface
    {
        $diff = $to->diff($from);
        \assert($diff instanceof \DateInterval);

        if ($diff->y || $diff->m || $diff->d || $diff->h || $diff->m) {
            // Diff is enought by minutes, just add one second to from.
            return $from->modify('+1 sec');
        }

        if (1 < $diff->s) {
            return $from->modify('+1 sec');
        }

        // Damn! Diff is less or equal than a single second. PostgreSQL storage
        // has a microsecond precision, so we can actually afford to add one
        // microsec. here, but for some other storage backends, this will not
        // work. This means that in the end, we will need to shift revisions
        // in the stream to ensure correct ordering as well.
        return $from->modify('+1 msec');
    }

    /**
     * Get or create empty namespace map.
     */
    private function getNamespaceMap(): NamespaceMap
    {
        return $this->namespaceMap ?? ($this->namespaceMap = new NamespaceMap());
    }

    /**
     * Normalize exception trace.
     */
    private function normalizeExceptionTrace(\Throwable $exception): string
    {
        $output = '';
        do {
            if ($output) {
                $output .= "\n";
            }
            $output .= \sprintf("%s: %s\n", \get_class($exception), $exception->getMessage());
            $output .= $exception->getTraceAsString();
        } while ($exception = $exception->getPrevious());

        return $output;
    }
}

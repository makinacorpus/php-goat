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
        throw new \Exception("Not implemented yet.");
    }

    /**
     * {@inheritdoc}
     */
    public function moveAtDate(Event $event, \DateTimeInterface $newDate): EventBuilder
    {
        throw new \Exception("Not implemented yet.");
    }

    /**
     * {@inheritdoc}
     */
    public function insertAfter(int $afterRevision): EventBuilder
    {
        throw new \Exception("Not implemented yet.");
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
                    $properties[Property::MODIFIED_PREVIOUS_NAME] = $event->validAt()->format(\DateTime::ISO8601);
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

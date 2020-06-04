<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore;

use Goat\Domain\DebuggableTrait;
use Goat\Domain\Event\BrokenMessage;
use Goat\Domain\Serializer\MimeTypeConverter;
use Psr\Log\NullLogger;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\SerializerInterface;

abstract class AbstractEventStore implements EventStore
{
    use DebuggableTrait;

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
     * {@inheritdoc}
     */
    final public function store(object $message, ?UuidInterface $aggregateId = null, ?string $aggregateType = null, bool $failed = false, array $extra = []): Event
    {
        @\trigger_error(\sprintf("%s::store() is deprecated", EventStore::class), E_USER_DEPRECATED);

        $builder = $this
            ->append()
            ->message($message)
            ->aggregate($aggregateType, $aggregateId)
        ;

        if (isset($extra['properties'])) {
            foreach ($extra['properties'] as $key => $value) {
                $builder->property($key, $value);
            }
        }

        if ($failed) {
            @\trigger_error(\sprintf("%s::store() with \$failed parameter is not supported anymore", EventStore::class), E_USER_DEPRECATED);
        }

        return $builder->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function append(): EventBuilder
    {
        return new DefaultEventBuilder(function (DefaultEventBuilder $builder) {
            $nameMap = $this->getNameMap();

            $message = $builder->getMessage();
            $aggregateId = $builder->getAggregateId();
            $aggregateType = $builder->getAggregateType();
            $properties = $builder->getProperties();

            $event = Event::create($message);

            // Compute normalized aggregate type, otherwise the PHP native class
            // or type name would be stored in database, we sure don't want that.
            $aggregateType = $nameMap->getName($aggregateType ?? $event->getAggregateType());
            if (!$aggregateId) {
                $aggregateId = $event->getAggregateId();
            }

            // Compute normalized event type.
            if ($eventType = $nameMap->getMessageName($message)) {
                $properties['type'] = $eventType;
            }

            // Compute normalized event name.
            $eventName = $nameMap->getName($event->getName());

            $logContext = [
                'aggregate_id' => (string)$aggregateId,
                'aggregate_type' => $aggregateType,
                'event' => $event,
                'event_name' => $eventName,
            ];

            // Compute necessary common properties. Custom properties from
            // caller might be overriden, the store is authoritative on some
            // of them.
            foreach ($this->computeProperties($event) as $key => $value) {
                $properties[$key] = $value;
            }

            $callback = \Closure::bind(
                static function (Event $event) use ($aggregateId, $aggregateType, $eventName, $properties): Event {
                    $event->aggregateId = $aggregateId;
                    $event->aggregateType = $aggregateType;
                    $event->name = $eventName;
                    $event->properties = $properties;
                    return $event;
                },
                null, Event::class
            );

            try {
                $newEvent = $this->doStore($callback($event));
                $this->logger->debug("Event stored", $logContext);

                return $newEvent;

            } catch (\Throwable $e) {
                $this->logger->critical("Event could not be stored", $logContext + ['exception' => $e]);

                throw $e;
            }
        });
    }

    /**
     * Update event metadata.
     */
    public function update(Event $event, array $properties): Event
    {
        $logContext = [
            'event' => $event,
        ];

        $callback = \Closure::bind(
            static function (Event $event) use ($properties): Event {
                $event = clone $event;
                // Soft alter properties.
                foreach ($properties as $key => $value) {
                    if (null === $value || '' === $value) {
                        unset($event->properties[$key]);
                    } else {
                        $event->properties[$key] = (string)$value;
                    }
                }
                return $event;
            },
            null, Event::class
        );

        try {
            $newEvent = $this->doUpdate($callback($event));
            $this->logger->debug("Event updated", $logContext);

            return $newEvent;

        } catch (\Throwable $e) {
            $this->logger->critical("Event could not be updated", $logContext + ['exception' => $e]);

            throw $e;
        }
    }

    /**
     * Mark event as failed and update metadata.
     */
    public function failedWith(Event $event, \Throwable $exception, array $properties = []): Event
    {
        $logContext = [
            'event' => $event,
        ];

        $code = $exception->getCode();
        $message = $exception->getMessage();
        $trace = $this->normalizeExceptionTrace($exception);

        $callback = \Closure::bind(
            static function (Event $event) use ($properties, $message, $code, $trace): Event {
                $event = clone $event;
                $event->hasFailed = true;
                $event->errorCode = $code;
                $event->errorMessage = $message;
                $event->errorTrace = $trace;
                // Soft alter properties.
                foreach ($properties as $key => $value) {
                    if (null === $value || '' === $value) {
                        unset($event->properties[$key]);
                    } else {
                        $event->properties[$key] = (string)$value;
                    }
                }
                return $event;
            },
            null, Event::class
        );

        try {
            $newEvent = $this->doUpdate($callback($event));
            $this->logger->debug("Event updated", $logContext);

            return $newEvent;

        } catch (\Throwable $e) {
            $this->logger->critical("Event could not be updated", $logContext + ['exception' => $e]);

            throw $e;
        }
    }

    /**
     * Real storage implementation, event is already fully populated to the
     * exception of revision and position. properties can be updated too.
     */
    abstract protected function doStore(Event $event): Event;

    /**
     * Real update implementation.
     *
     * You must NOT change other values than properties and error message
     * information, all information that identifies the aggregate or the
     * event MUST remain untouched, or here be dragons.
     */
    abstract protected function doUpdate(Event $event): Event;

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
     * Get or create empty namespace map.
     */
    final protected function getNameMap(): NameMap
    {
        return $this->nameMap ?? ($this->nameMap = new DefaultNameMap());
    }

    /**
     * Get namespace for aggregate type.
     */
    final protected function getNamespace(string $aggregateType): string
    {
        return $this->getNamespaceMap()->getNamespace($aggregateType);
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
     * Compute event properties that can be computed automatically.
     */
    final private function computeProperties(Event $event): array
    {
        $properties = $event->getProperties();
        $message = $event->getMessage();

        if (\is_object($message)) {
            $properties[Property::CONTENT_TYPE] = $this->getSerializationFormat();
            if (empty($properties[Property::MESSAGE_TYPE])) {
                $properties[Property::MESSAGE_TYPE] =  \get_class($message);
            }
        } else {
            $properties[Property::CONTENT_TYPE] = 'text/plain';
            if (empty($properties[Property::MESSAGE_TYPE])) {
                $properties[Property::MESSAGE_TYPE] =  \gettype($message);
            }
        }

        return $properties;
    }

    /**
     * Get or create empty namespace map.
     */
    final private function getNamespaceMap(): NamespaceMap
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

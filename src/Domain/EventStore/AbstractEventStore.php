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
     * Compute event properties that can be computed automatically.
     */
    final protected function computeProperties(Event $event): array
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
     * Serialize event data.
     *
     * You may, or may not, use this method.
     */
    final protected function messageToString(Event $event): string
    {
        return $this->getSerializer()->serialize(
            $event->getMessage(),
            MimeTypeConverter::mimetypeToSerializer($this->getSerializationFormat())
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
     * Get or create empty namespace map
     */
    final protected function getNameMap(): NameMap
    {
        return $this->nameMap ?? ($this->nameMap = new DefaultNameMap());
    }

    /**
     * {@inheritdoc}
     */
    final public function setNameMap(NameMap $nameMap): void
    {
        $this->nameMap = $nameMap;
    }

    /**
     * Get or create empty namespace map
     */
    final private function getNamespaceMap(): NamespaceMap
    {
        return $this->namespaceMap ?? ($this->namespaceMap = new NamespaceMap());
    }

    /**
     * {@inheritdoc}
     */
    final public function setNamespaceMap(NamespaceMap $namespaceMap): void
    {
        $this->namespaceMap = $namespaceMap;
    }

    /**
     * Get namespace for aggregate type
     */
    final protected function getNamespace(string $aggregateType): string
    {
        return $this->getNamespaceMap()->getNamespace($aggregateType);
    }

    /**
     * Get serialization format
     */
    final protected function getSerializationFormat(): string
    {
        return $this->serializerFormat ?? Property::DEFAULT_CONTENT_TYPE;
    }

    /**
     * Get namespace for aggregate type
     */
    final protected function getSerializer(): SerializerInterface
    {
        return $this->serializer;
    }

    /**
     * Real storage implementation, event is already fully populated to the
     * exception of revision and position. properties can be updated too.
     */
    abstract protected function doStore(Event $event): Event;

    /**
     * {@inheritdoc}
     */
    final public function store(object $message, ?UuidInterface $aggregateId = null, ?string $aggregateType = null, bool $failed = false, array $extra = []): Event
    {
        $event = Event::create($message);
        $nameMap = $this->getNameMap();

        // Compute normalized aggregate type, otherwise the PHP native class
        // or type name would be stored in database, we sure don't want that.
        $aggregateType = $nameMap->getName($aggregateType ?? $event->getAggregateType());
        if (!$aggregateId) {
            $aggregateId = $event->getAggregateId();
        }

        // Compute normalized event type.
        if ($eventType = $nameMap->getMessageName($message)) {
            $extra['properties']['type'] = $eventType;
        }

        // Compute normalized event name.
        $eventName = $nameMap->getName($event->getName());

        $logContext = [
            'aggregate_id' => (string)$aggregateId,
            'aggregate_type' => $aggregateType,
            'event' => $event,
            'event_name' => $eventName,
        ];

        $callback = \Closure::bind(
            static function (Event $event) use ($failed, $aggregateId, $aggregateType, $eventName, $extra): Event {
                $event->aggregateId = $aggregateId;
                $event->aggregateType = $aggregateType;
                $event->errorCode = $extra['error_code'] ?? null;
                $event->errorMessage = $extra['error_message'] ?? null;
                $event->errorTrace = $extra['error_trace'] ?? null;
                $event->name = $eventName;
                $event->hasFailed = $failed;
                $event->properties = $extra['properties'];
                return $event;
            },
            null, Event::class
        );

        try {
            $event = $this->doStore($callback($event));
            $this->logger->debug("Event stored", $logContext);
        } catch (\Throwable $e) {
            $this->logger->critical("Event could not be stored", $logContext + ['exception' => $e]);

            throw $e;
        }

        return $event;
    }

    /**
     * Set serializer
     */
    final public function setSerializer(SerializerInterface $serializer, ?string $format = null): void
    {
        $this->serializer = $serializer;
        $this->serializerFormat = $format;
    }
}

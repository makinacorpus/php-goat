<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore;

use Goat\Domain\Event\BrokenMessage;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\SerializerInterface;

abstract class AbstractEventStore implements EventStore
{
    private $nameMap;
    private $namespaceMap;
    private $serializer;
    private $serializerFormat;

    /**
     * Mimetype to Symfony serializer type
     */
    protected function mimetypeToSerializer(string $mimetype)
    {
        if (false !== \stripos($mimetype, 'json')) {
            return 'json';
        }
        if (false !== \stripos($mimetype, 'xml')) {
            return 'xml';
        }
        return $mimetype;
    }

    /**
     * Symfony serializer to mime type.
     */
    protected function serializerToMimetype(string $type)
    {
        switch ($type) {
            case 'json':
                return 'application/json';
            case 'xml':
                return 'application/xml';
            default:
                return $type;
        }
    }

    /**
     * Compute event properties that can be computed automatically.
     */
    final protected function computeProperties(Event $event): array
    {
        $properties = $event->getProperties();
        $message = $event->getMessage();

        if (\is_object($message)) {
            $properties[Event::PROP_CONTENT_TYPE] = $this->getSerializationFormat();
            if (empty($properties[Event::PROP_MESSAGE_TYPE])) {
                $properties[Event::PROP_MESSAGE_TYPE] =  \get_class($message);
            }
        } else {
            $properties[Event::PROP_CONTENT_TYPE] = 'text/plain';
            if (empty($properties[Event::PROP_MESSAGE_TYPE])) {
                $properties[Event::PROP_MESSAGE_TYPE] =  \gettype($message);
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
            $this->mimetypeToSerializer($this->getSerializationFormat())
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

        if ('string' === ($properties[Event::PROP_CONTENT_TYPE] ?? $defaultFormat)) {
            // @todo denormalize scalar values using PROP_MESSAGE_TYPE
            //   as PHP native type to denormalize.
            return $data;
        }

        return $this->serializer->deserialize(
            \is_resource($data) ? \stream_get_contents($data) : $data,
            $properties[Event::PROP_MESSAGE_TYPE] ?? $eventName,
            $this->mimetypeToSerializer(
                $properties[Event::PROP_CONTENT_TYPE] ?? $defaultFormat
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
    final private function getNameMap(): NameMap
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
        return $this->serializerFormat ?? Event::DEFAULT_CONTENT_TYPE;
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

        // Compute normalized event type.
        if ($eventType = $nameMap->getMessageName($message)) {
            $extra['properties']['type'] = $eventType;
        }

        // Compute normalized event name.
        $eventName = $nameMap->getName($event->getName());

        $callback = \Closure::bind(
            function (Event $event) use ($failed, $aggregateId, $aggregateType, $eventName, $extra): Event {
                $event->aggregateId = $aggregateId ?? $event->getAggregateId();
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

        return $this->doStore($callback($event));
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

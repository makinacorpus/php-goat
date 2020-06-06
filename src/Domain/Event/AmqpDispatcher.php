<?php

declare(strict_types=1);

namespace Goat\Domain\Event;

use Goat\Domain\EventStore\DefaultNameMap;
use Goat\Domain\EventStore\NameMap;
use Goat\Domain\EventStore\Property;
use MakinaCorpus\AMQP\Patterns\PatternFactory;
use MakinaCorpus\AMQP\Patterns\Publisher;
use MakinaCorpus\AMQP\Patterns\Routing\DefaultRouteMap;
use MakinaCorpus\AMQP\Patterns\Routing\Route;
use MakinaCorpus\AMQP\Patterns\Routing\RouteMap;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Dispatches messages on AMQP.
 *
 * @experimental
 * @codeCoverageIgnore
 * @deprecated
 *   This code is dead, but kept here until we do implement AMQP correctly.
 */
final class AmqpDispatcher extends AbstractDirectDispatcher
{
    /** @var PatternFactory */
    private $factory;

    /** @var NameMap */
    private $nameMap;

    /** @var RouteMap */
    private $routingMap;

    /** @var SerializerInterface */
    private $serializer;

    /** @var Publisher[] */
    private $publishers = [];

    /**
     * Default constructor
     */
    public function __construct(
        HandlersLocatorInterface $handlersLocator,
        PatternFactory $factory,
        SerializerInterface $serializer,
        ?NameMap $nameMap = null,
        ?RouteMap $routingMap = null
    ) {
        parent::__construct($handlersLocator);

        $this->factory = $factory;
        $this->nameMap = $nameMap ?? new DefaultNameMap();
        $this->routingMap = $routingMap ?? new DefaultRouteMap();
        $this->serializer = $serializer;
    }

    /**
     * Content type to serializer format conversion.
     *
     * Ideally, the serializer itself should deal with this.
     */
    private function contentTypeToSerializerFormat(string $contentType): string
    {
        if (false !== \stripos($contentType, 'json')) {
            return 'json';
        }
        if (false !== \stripos($contentType, 'xml')) {
            return 'xml';
        }
        if (false !== \stripos($contentType, 'csv')) {
            return 'csv';
        }
        return $contentType;
    }

    /**
     * Get publisher for route.
     *
     * Internally we share publishers on an exchange basis, to avoid creating
     * too many channels and consume too much memory.
     */
    private function getPublisher(Route $route): Publisher
    {
        $id = $route->getHash();

        if ($publisher = ($this->publishers[$id] ?? null)) {
            return $publisher;
        }

        return $this->publishers[$id] = $this
            ->factory
            ->createPublisher($route->getExchange())
            ->exchangeType($route->getExchangeType())
        ;
    }

    /**
     * Send message in bus.
     *
     * Procedure is the same for dispatch command and event, since both are
     * being sent in AMQP broker, difference between command and event will
     * be configured at the addressing/routing level, not here.
     */
    private function sendMessage(MessageEnvelope $envelope): void
    {
        $message = $envelope->getMessage();

        $name = $this->nameMap->getMessageName($message);
        $route = $this->routingMap->getRouteFor($name);
        $contentType = $route->getContentType();

        $properties = Property::toAmqpProperties($envelope->getProperties());
        $properties['type'] = $name;
        $properties['content_type'] = $contentType;

        $serialized = $this->serializer->serialize(
            $message,
            $this->contentTypeToSerializerFormat($contentType)
        );

        $this->getPublisher($route)->publish($serialized, $properties, $route->getRoutingKey());
    }

    /**
     * {@inheritdoc}
     */
    protected function doAsynchronousCommandDispatch(MessageEnvelope $envelope): void
    {
        $this->sendMessage($envelope);
    }

    /**
     * {@inheritdoc}
     */
    protected function doAsynchronousEventDispatch(MessageEnvelope $envelope): void
    {
        $this->sendMessage($envelope);
    }
}

<?php

declare(strict_types=1);

namespace Goat\Domain\Messenger;

use Goat\Domain\EventStore\NameMap;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Wrap the current serializer in order to give aliased message types to the
 * transport, and allow transparent arbitrary message naming.
 */
final class NameMapMessengerSerializer implements SerializerInterface
{
    /** @var NameMap */
    private $nameMap;

    /** @var SerializerInterface */
    private $decorated;

    /**
     * Default constructor
     */
    public function __construct(NameMap $nameMap, SerializerInterface $serializer)
    {
        $this->decorated = $serializer;
        $this->nameMap = $nameMap;
    }

    /**
     * {@inheritdoc}
     */
    public function encode(Envelope $envelope): array
    {
        $ret = $this->decorated->encode($envelope);

        if (isset($ret['headers']['type'])) {
            $ret['headers']['type'] = $this->nameMap->getName($ret['headers']['type']);
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        if (isset($encodedEnvelope['headers']['type'])) {
            $encodedEnvelope['headers']['type'] = $this->nameMap->getType($encodedEnvelope['headers']['type']);
        }

        return $this->decorated->decode($encodedEnvelope);
    }
}

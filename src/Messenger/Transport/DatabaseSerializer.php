<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\Messenger\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class DatabaseSerializer implements SerializerInterface
{
    const HEADER_DEBUG = 'X-Sender-Debug';
    const HEADER_ENV = 'X-Sender-Env';
    const HEADER_SIGNATURE = 'X-Sender-Signature';
    const HEADER_TYPE = 'type';

    private $debug = false;
    private $environment;
    private $signature;

    /**
     * Default constructor
     */
    public function __construct(?string $signature, ?string $environment, bool $debug = false)
    {
        $this->debug = $debug;
        $this->environment = $environment;
        $this->signature = $signature;
    }

    /**
     * {@inheritdoc}
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        if (empty($encodedEnvelope['body']) || empty($encodedEnvelope['headers'])) {
            throw new \InvalidArgumentException('Encoded envelope should have at least a `body` and some `headers`.');
        }

        $envelopeItems = isset($encodedEnvelope['headers']['X-Message-Envelope-Items']) ? \unserialize($encodedEnvelope['headers']['X-Message-Envelope-Items']) : [];
        $message = \unserialize(\base64_decode($encodedEnvelope['body']));

        return new Envelope($message, $envelopeItems);
    }

    /**
     * {@inheritdoc}
     */
    public function encode(Envelope $envelope): array
    {
        $headers = [
            self::HEADER_TYPE => \get_class($envelope->getMessage()),
        ];
        if ($this->debug) {
            $headers[self::HEADER_DEBUG] = "true";
        }
        if ($this->environment) {
            $headers[self::HEADER_ENV] = (string)$this->environment;
        }
        if ($this->signature) {
            $headers[self::HEADER_SIGNATURE] = (string)$this->signature;
        }

        if ($configurations = $envelope->all()) {
            $headers['X-Message-Envelope-Items'] = \serialize($configurations);
        }

        return ['body' => \base64_encode(\serialize($envelope->getMessage())), 'headers' => $headers];
    }
}

<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\Messenger\Transport;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class DefaultDatabaseSerializer implements SerializerInterface
{
    const HEADER_DEBUG = 'X-Sender-Debug';
    const HEADER_ENV = 'X-Sender-Env';
    const HEADER_TYPE = 'type';

    private $base64encode = true;
    private $debug = false;
    private $environment;

    /**
     * Default constructor
     */
    public function __construct(bool $base64encode = true, bool $debug = false, ?string $environment = null)
    {
        $this->base64encode = $base64encode;
        $this->debug = $debug;
        $this->environment = $environment;
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
        if ($this->base64encode) { // Ideally, an automatic detection would be better.
            $message = \unserialize(\base64_decode($encodedEnvelope['body']));
        } else {
            $message = \unserialize($encodedEnvelope['body']);
        }

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

        if ($configurations = $envelope->all()) {
            $headers['X-Message-Envelope-Items'] = \serialize($configurations);
        }

        if ($this->base64encode) {
            return ['body' => \base64_encode(\serialize($envelope->getMessage())), 'headers' => $headers];
        }
        return ['body' => \serialize($envelope->getMessage()), 'headers' => $headers];
    }
}

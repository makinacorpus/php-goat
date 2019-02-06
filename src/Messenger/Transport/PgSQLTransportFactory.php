<?php

namespace Goat\Bridge\Symfony\Messenger\Transport;

use Goat\Runner\Runner;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class PgSQLTransportFactory implements TransportFactoryInterface
{
    private $debug;
    private $environment;
    private $runner;
    private $serializer;

    /**
     * Default constructor
     */
    public function __construct(Runner $runner, ?SerializerInterface $serializer = null, $debug = false, ?string $environment = null)
    {
        $this->debug = $debug;
        $this->runner = $runner;
        $this->environment = $environment;

        if (!$serializer) {
            if (\class_exists('\\Symfony\\Component\\Serializer\\SerializerInterface'::class)) {
                $serializer = Serializer::create();
            } else {
                $serializer = new DefaultDatabaseSerializer(false, $this->debug, $this->environment);
            }
        }
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function createTransport(string $dsn, array $options): TransportInterface
    {
        // @todo use dsn to determine connection to use (as of now, always default)
        return new PgSQLTransport($this->runner, $this->serializer, $options, $this->debug);
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $dsn, array $options): bool
    {
        return 0 === \stripos($dsn, 'goat://');
    }
}

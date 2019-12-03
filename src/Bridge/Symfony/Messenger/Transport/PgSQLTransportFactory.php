<?php

namespace Goat\Bridge\Symfony\Messenger\Transport;

use Goat\Runner\Runner;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class PgSQLTransportFactory implements TransportFactoryInterface
{
    private $debug;
    private $environment;
    private $runner;

    /**
     * Default constructor
     */
    public function __construct(Runner $runner, bool $debug = false, ?string $environment = null)
    {
        $this->debug = (bool)$debug;
        $this->runner = $runner;
        $this->environment = $environment;
    }

    /**
     * {@inheritdoc}
     */
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        // @todo use dsn to determine connection to use (as of now, always default)
        return new PgSQLTransport($this->runner, $serializer, $options, $this->debug);
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $dsn, array $options): bool
    {
        return 0 === \stripos($dsn, 'goat://');
    }
}

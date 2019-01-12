<?php

namespace Goat\Bridge\Symfony\Messenger\Transport;

use Goat\Runner\Runner;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class PgSQLTransportFactory implements TransportFactoryInterface
{
    private $encoder;
    private $decoder;
    private $debug;
    private $runner;

    /**
     * Default constructor
     */
    public function __construct(Runner $runner, bool $debug = false)
    {
        $this->debug = $debug;
        $this->runner = $runner;
    }

    /**
     * {@inheritdoc}
     */
    public function createTransport(string $dsn, array $options): TransportInterface
    {
        // @todo use dsn to determine connection to use (as of now, always default)
        return new PgSQLTransport($this->runner, $options, $this->debug);
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $dsn, array $options): bool
    {
        return 0 === \stripos($dsn, 'goat://');
    }
}

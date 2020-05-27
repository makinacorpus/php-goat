<?php

namespace Goat\Bridge\Symfony\Messenger\Transport;

use Goat\Domain\MessageBroker\PgSQLMessageBroker;
use Goat\Runner\Runner;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface as MessengerSerializer;
use Symfony\Component\Serializer\SerializerInterface;

final class PgSQLTransportFactory implements TransportFactoryInterface
{
    private bool $debug;
    private string $environment;
    private Runner $runner;
    private SerializerInterface $serializer;

    /**
     * Default constructor
     */
    public function __construct(Runner $runner, SerializerInterface $serializer, bool $debug = false, ?string $environment = null)
    {
        $this->debug = (bool)$debug;
        $this->environment = $environment;
        $this->runner = $runner;
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function createTransport(string $dsn, array $options, MessengerSerializer $serializer): TransportInterface
    {
        // @todo
        //   This will completely ignore messenger configuration, such as DSN
        //   and other stuff like that, moreover, it also will completely ignore
        //   the messenger own serializer (which we do NOT want to use anyway).
        return new MessageBrokerTransport(
            new PgSQLMessageBroker(
                $this->runner,
                $this->serializer,
                $options,
                $this->debug
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $dsn, array $options): bool
    {
        return 0 === \stripos($dsn, 'goat://');
    }
}

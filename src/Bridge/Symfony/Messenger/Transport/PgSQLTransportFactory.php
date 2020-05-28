<?php

namespace Goat\Bridge\Symfony\Messenger\Transport;

use Goat\Domain\MessageBroker\MessageBroker;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface as MessengerSerializer;

final class PgSQLTransportFactory implements TransportFactoryInterface
{
    private MessageBroker $messageBroker;

    /**
     * Default constructor
     */
    public function __construct(MessageBroker $messageBroker)
    {
        $this->messageBroker = $messageBroker;
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
        // @todo
        //   Allow controlling the $keepCustomEnvelope using $options config.
        return new MessageBrokerTransport($this->messageBroker, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $dsn, array $options): bool
    {
        return 0 === \stripos($dsn, 'goat://');
    }
}

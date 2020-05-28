<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\Messenger\Transport;

use Goat\Domain\Event\MessageEnvelope;
use Goat\Domain\EventStore\Property;
use Goat\Domain\MessageBroker\MessageBroker;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class MessageBrokerTransport implements TransportInterface
{
    private bool $keepCustomEnvelope = true;
    private string $queue;
    private array $options;
    private MessageBroker $messageBroker;

    /**
     * @param bool $keepCustomEnvelope
     *   If this is set to true (default) then custom MessageEnvelope instances
     *   will be passed to bus dispatch, which means that our custom messenger
     *   middleware will catch them and process them our way.
     *   If set to false, the message will directly be injected to instead and
     *   we will loose all our custom headers. It will still work thought, just
     *   the result in the event store might differ from what you would expect. 
     */
    public function __construct(MessageBroker $messageBroker, array $options = [], bool $keepCustomEnvelope = true)
    {
        $this->keepCustomEnvelope = $keepCustomEnvelope;
        $this->messageBroker = $messageBroker;
        $this->options = $options;
        $this->queue = $options['queue'] ?? 'default'; // @todo What to do with this?
    }

    /**
     * {@inheritdoc}
     */
    public function get(): iterable
    {
        $envelope = $this->messageBroker->get();

        if (!$envelope) {
            return [];
        }

        return [$this->toSymfonyEnvelope($envelope)];
    }

    /**
     * {@inheritdoc}
     */
    public function ack(Envelope $envelope): void
    {
        $this->messageBroker->ack($this->fromSymfonyEnvelope($envelope));
    }

    /**
     * {@inheritdoc}
     */
    public function reject(Envelope $envelope): void
    {
        $this->messageBroker->reject($this->fromSymfonyEnvelope($envelope));
    }

    /**
     * {@inheritdoc}
     */
    public function send(Envelope $envelope): Envelope
    {
        $this->messageBroker->dispatch($this->fromSymfonyEnvelope($envelope));
    }

    /**
     * Convert custom envelope to symfony/messenger envelope.
     */
    private function toSymfonyEnvelope(MessageEnvelope $envelope): Envelope
    {
        return Envelope::wrap(
            $this->keepCustomEnvelope ? $envelope : $envelope->getMessage(),
            self::toSymfonyStamps($envelope)
        );
    }

    /**
     * Convert symfony/messenger envelope to custom envelope.
     */
    private function fromSymfonyEnvelope(Envelope $envelope): MessageEnvelope
    {
        return MessageEnvelope::wrap(
            $envelope->getMessage(),
            self::fromSymfonyStamps($envelope->all())
        );
    }

    /**
     * Converts properties to symfony/messenger stamps.
     *
     * @todo Actually symfony/messenger stamps can have duplicates.
     *   I don't know how to solve this.
     */
    public static function toSymfonyStamps(MessageEnvelope $envelope): array
    {
        $ret = [];

        if ($messageId = $envelope->getMessageId()) {
            $ret[] = new Stamp\TransportMessageIdStamp($messageId);
        }

        foreach ($envelope->getProperties() as $name => $value) {
            switch ($name) {

                case Property::RETRY_DELAI:
                    $ret[] = new Stamp\DelayStamp((int)$value);
                    break;

                case Property::RETRY_COUNT:
                    $ret[] = new Stamp\RedeliveryStamp((int)$value);
                    break;

                case 'x-symfony-bus-name':
                    $ret[] = new Stamp\BusNameStamp($value);
                    break;

                case 'x-symfony-dispatch-after-bus':
                    if ($value) {
                        $ret[] = new Stamp\DispatchAfterCurrentBusStamp();
                    }
                    break;

                case 'x-symfony-validation-groups':
                    $ret[] = new Stamp\ValidationStamp(\explode(',', $value));
                    break;

                /*
                 * @todo May be this one could be useful? Investigate.
                 *
                case 'x-symfony-':
                    $ret[] = new Stamp\SentToFailureTransportStamp();
                    break;
                 */

                /*
                 * Why would I want to identify who sent it?
                 *
                case 'x-symfony-':
                    $ret[] = new Stamp\SentStamp();
                    break;
                 */

                /*
                 * I think this one will be added automatically.
                 *
                case 'x-symfony-':
                    $ret[] = new Stamp\ReceivedStamp();
                    break;
                 */

                /*
                 * I think this one will be added automatically.
                 *
                case 'x-symfony-':
                    $ret[] = new Stamp\ConsumedByWorkerStamp();
                    break;
                 */

                /*
                 * We are fetching a non-processed message yet.
                 *
                case 'x-symfony-':
                    $ret[] = new Stamp\HandledStamp();
                    break;
                 */

                /*
                 * I do not think that this one will ever be useful to us.
                 *
                case 'x-symfony-serializer-context':
                    $ret[] = new Stamp\SerializerStamp();
                    break;
                 */
            }
        }

        return $ret;
    }

    /**
     * Convert symfony/messenger stamps to properties.
     *
     * @todo Actually symfony/messenger stamps can have duplicates.
     *   I don't know how to solve this.
     */
    public static function fromSymfonyStamps(iterable $stamps): array
    {
        $ret = [];

        foreach ($stamps as $stamp) {
            if ($stamp instanceof Stamp\TransportMessageIdStamp) {
                $ret[Property::MESSAGE_ID] = $stamp;
            }
            if ($stamp instanceof Stamp\DelayStamp) {
                $ret[Property::RETRY_DELAI] = (string)$stamp->getDelay();
            }
            if ($stamp instanceof Stamp\RedeliveryStamp) {
                $ret[Property::RETRY_COUNT] = (string)$stamp->getRetryCount();
            }
            if ($stamp instanceof Stamp\BusNameStamp) {
                $ret['x-symfony-bus-name'] = (string)$stamp->getBusName();
            }
            if ($stamp instanceof Stamp\DispatchAfterCurrentBusStamp) {
                $ret['x-symfony-dispatch-after-bus'] = "1";
            }
            if ($stamp instanceof Stamp\ValidationStamp) {
                $ret['x-symfony-validation-groups'] = \implode(',', $stamp->getGroups());
            }
        }

        return $ret;
    }
}

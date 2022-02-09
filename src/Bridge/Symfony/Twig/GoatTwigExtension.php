<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\Twig;

use Goat\Dispatcher\MessageDescriptor\MessageDescriptor;
use MakinaCorpus\Message\DescribeableMessage;
use MakinaCorpus\Normalization\Serializer;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;

final class GoatTwigExtension extends AbstractExtension
{
    private bool $debug;
    private MessageDescriptor $messageDescriptor;
    private Serializer $serializer;

    public function __construct(MessageDescriptor $messageDescriptor, Serializer $serializer, bool $debug = false)
    {
        $this->debug = $debug;
        $this->messageDescriptor = $messageDescriptor;
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('message_describe', [$this, 'renderEventDescription']),
            new TwigFunction('message_message', [$this, 'renderEventMessage']),
        ];
    }

    /**
     * Render message description
     */
    private function renderMessageDescription($object): ?string
    {
        if ($object instanceof DescribeableMessage) {
            return (string) $object->describe();
        }

        return null;
    }

    /**
     * Render event description
     */
    public function renderEventDescription($event): ?string
    {
        return $this->messageDescriptor->describe($event);
    }

    /**
     * Render object serialized version
     */
    public function renderEventMessage($object, ?string $format = null): string
    {
        try {
            if (\is_scalar($object)) {
                return (string) $object;
            }

            return $this->serializer->serialize($object, $format ?? 'application/json');

        } catch (\Throwable $e) {
            if ($this->debug) {
                throw $e;
            }

            return $e->getMessage();
        }
    }
}

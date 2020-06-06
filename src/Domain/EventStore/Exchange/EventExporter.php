<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore\Exchange;

use Goat\Domain\EventStore\EventStream;
use Goat\Domain\EventStore\Property;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

/**
 * Export stream.
 *
 * @experimental
 * @codeCoverageIgnore
 */
final class EventExporter
{
    const FORMAT_IMPORT = 'binary';

    private $serializer;

    /**
     * Default constructor
     */
    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * Export stream
     */
    public function exportAs(EventStream $stream, $fileOrResource, ?string $format = null, bool $breakOnInvalidEvent = true)
    {
        $wasResource = false;
        $format = $format ?? Property::DEFAULT_CONTENT_TYPE;
        $handle = null;

        if (\is_resource($fileOrResource)) {
            $handle = $fileOrResource;
            $wasResource = true;
        } else if (\is_string($fileOrResource)) {
            if (\file_exists($fileOrResource)) {
                throw new \InvalidArgumentException(\sprintf("File '%s' already exists", $fileOrResource));
            }
            if (false === ($handle = \fopen($fileOrResource, "a+"))) {
                throw new \InvalidArgumentException(\sprintf("File '%s' could not be open for writing", $fileOrResource));
            }
        } else {
            throw new \InvalidArgumentException(\sprintf("Given file must a opened file resource or a valid file path, '%s' given", \gettype($fileOrResource)));
        }

        $context = [];
        if ('xml' === $format) {
            $context[XmlEncoder::ENCODER_IGNORED_NODE_TYPES] = [XML_PI_NODE];
            $context[XmlEncoder::ROOT_NODE_NAME] = 'event';
            \fwrite($handle, "<?xml version=\"1.0\"?>\n<eventlist>");
        }
        if (self::FORMAT_IMPORT === $format) {
            $date = (new \DateTimeImmutable())->format(\DateTime::ISO8601);
            $count = \count($stream);
            \fwrite($handle, <<<EOT
# Event store export.
#   - Import it at your own risks, enjoy!
# Exported at: {$date}
# Event count: {$count}

EOT
            );
        }

        /** @var \Goat\Domain\EventStore\Event $event */
        foreach ($stream as $event) {
            if (self::FORMAT_IMPORT === $format) {
                $message = $event->getMessage();
                \fwrite($handle, \get_class($message).':json:');
                \fwrite($handle, \base64_encode($this->serializer->serialize($message, 'json')));
            } else {
                $envelope = [
                    'properties' => $event->getProperties(),
                    'data' => $event->getMessage(),
                ];
                \fwrite($handle, $this->serializer->serialize($envelope, $format, $context));
            }
            \fwrite($handle, "\n");
        }

        if ('xml' === $format) {
            \fwrite($handle, "</eventlist>\n");
        }

        if (!$wasResource) {
            \fclose($handle);
        }
    }
}

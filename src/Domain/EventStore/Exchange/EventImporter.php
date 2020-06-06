<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore\Exchange;

use Symfony\Component\Serializer\SerializerInterface;

/**
 * Import stream.
 *
 * @experimental
 * @codeCoverageIgnore
 */
final class EventImporter
{
    private $serializer;

    /**
     * Default constructor
     */
    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * Load an iterator of messages
     */
    public function importFrom($fileOrResource, bool $breakOnInvalid = true, ?callable $onError = null): iterable
    {
        $wasResource = false;
        $handle = null;

        if (\is_resource($fileOrResource)) {
            $handle = $fileOrResource;
            $wasResource = true;
        } else if (\is_string($fileOrResource)) {
            if (!\file_exists($fileOrResource)) {
                throw new \InvalidArgumentException(\sprintf("File '%s' does not exist", $fileOrResource));
            }
            if (!\is_readable($fileOrResource)) {
                throw new \InvalidArgumentException(\sprintf("File '%s' cannot be read", $fileOrResource));
            }
            if (false === ($handle = \fopen($fileOrResource, "r"))) {
                throw new \InvalidArgumentException(\sprintf("File '%s' could not be open for reading", $fileOrResource));
            }
        } else {
            throw new \InvalidArgumentException(\sprintf("Given file must a opened file resource or a valid file path, '%s' given", \gettype($fileOrResource)));
        }

        try {
            while ($line = \fgets($handle)) {
                if (!$line || '#' === $line[0]) {
                    continue; // Ignore empty lines and comments.
                }
                list($class, $format, $data) = \explode(':', $line);
                try {
                    yield $this->serializer->deserialize(\base64_decode($data), $class, $format);
                } catch (\Throwable $e) {
                    $doReallyBreak = null;
                    if ($onError) {
                        if (\call_user_func($onError, $e)) {
                            $doReallyBreak = false;
                        }
                    }
                    if ($breakOnInvalid && $doReallyBreak) {
                        throw $e;
                    }
                    // Else continue.
                }
            }
        } finally {
            if ($wasResource) {
                \fclose($handle);
            }
        }
    }
}

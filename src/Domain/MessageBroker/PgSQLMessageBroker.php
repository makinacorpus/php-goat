<?php

declare(strict_types=1);

namespace Goat\Domain\MessageBroker;

use Goat\Domain\Event\BrokenMessage;
use Goat\Domain\Event\MessageEnvelope;
use Goat\Domain\EventStore\Property;
use Goat\Domain\Serializer\MimeTypeConverter;
use Goat\Runner\Runner;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Serializer\SerializerInterface;

final class PgSQLMessageBroker implements MessageBroker, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private bool $debug = false;
    private string $contentType;
    private string $queue;
    private array $options; 
    private Runner $runner;
    private SerializerInterface $serializer;

    public function __construct(Runner $runner, SerializerInterface $serializer, array $options = [], bool $debug = false)
    {
        $this->contentType = $options['content_type'] ?? Property::DEFAULT_CONTENT_TYPE;
        $this->debug = $debug;
        $this->logger = new NullLogger();
        $this->options = $options;
        $this->queue = $options['queue'] ?? 'default';
        $this->runner = $runner;
        $this->serializer = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public function get(): ?MessageEnvelope
    {
        $data = $this
            ->runner
            ->execute(
                <<<SQL
                UPDATE "message_broker"
                SET
                    "consumed_at" = now()
                WHERE
                    "id" IN (
                        SELECT "id"
                        FROM "message_broker"
                        WHERE
                            "queue" = ?::string
                            AND "consumed_at" is null
                        ORDER BY
                            "serial" ASC
                        LIMIT 1 OFFSET 0
                    )
                    AND "consumed_at" IS NULL
                RETURNING
                    "id",
                    "serial",
                    "headers",
                    "type",
                    "content_type",
                    "body"::bytea
                SQL,
                [$this->queue]
            )
            ->fetch()
        ;

       if ($data) {
            try {
                if (\is_resource($data['body'])) { // Bytea
                    $body = \stream_get_contents($data['body']);
                } else {
                    $body = $data['body'];
                }

                if (empty($data['type'])) {
                    $type = $data['headers'][Property::MESSAGE_TYPE] ?? null;
                } else {
                    // Type in database is authoritative.
                    $type = $data['headers'][Property::MESSAGE_TYPE] = $data['type'];
                }

                if (empty($data['content_type'])) {
                    $contentType = $data['headers'][Property::CONTENT_TYPE] ?? null;
                } else {
                    // Content type in database is authoritative.
                    $contentType = $data['headers'][Property::CONTENT_TYPE] =  $data['content_type'];
                }
                // For symfony/messenger compatibility.
                $data['headers']['Content-Type'] = $contentType;

                // For debugging purpose mostly.
                $data['headers']['x-serial'] = (string)$data['serial'];

                if ($contentType && $type) {
                    $message = $this
                        ->serializer
                        ->deserialize(
                            $body,
                            $type,
                            MimeTypeConverter::mimetypeToSerializer($contentType)
                        )
                    ;
                } else {
                    $message = new BrokenMessage(null, null, $body, $type);
                }

                return MessageEnvelope::wrap($message, $data['headers']);

            } catch (\Throwable $e) {
                $this->markAsFailed((string)$data['id']);

                throw new TransportException('Error while fetching messages', 0, $e);
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(MessageEnvelope $envelope): void
    {
        $messageId = Uuid::uuid4()->toString();

        // Reset message id if there is one, for the simple and unique reason
        // that the application could arbitrary resend a message as a new
        // message at anytime, and message identifier is the unique key.
        if ($envelope->hasProperty(Property::MESSAGE_ID)) {
            $envelope = $envelope->withProperties([Property::MESSAGE_ID => $messageId]);
        }

        $message = $envelope->getMessage();
        $type = $envelope->getProperty(Property::MESSAGE_TYPE, \get_class($message));

        $contentType = $envelope->getProperty(Property::CONTENT_TYPE);
        if (!$contentType) {
            // For symfony/messenger compatibility.
            $contentType = $envelope->getProperty('Content-Type', $this->contentType);
        }

        $data = $this->serializer->serialize($message, MimeTypeConverter::mimetypeToSerializer($contentType));

        $this->runner->execute(
            <<<SQL
            INSERT INTO "message_broker"
                (id, queue, headers, type, content_type, body)
            VALUES
                (?::uuid, ?::string, ?::json, ?, ?, ?::bytea)
            SQL
           , [
               $messageId,
               $this->queue,
               $envelope->getProperties(),
               $type,
               $contentType,
               $data,
           ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function ack(MessageEnvelope $envelope): void
    {
        // Nothing to do, ACK was atomic in the UPDATE/RETURNING SQL query.
    }

    /**
     * {@inheritdoc}
     */
    public function reject(MessageEnvelope $envelope): void
    {
        // @todo Handle retry here.

        $messageId = $envelope->getMessageId();

        if ($messageId) {
            $this->markAsFailed($messageId);
        }
    }

    /**
     * Mark single message as failed.
     */
    private function markAsFailed(string $id): void
    {
        $this->runner->execute(
            <<<SQL
            UPDATE "message_broker"
            SET
                "has_failed" = true
            WHERE
                "id" = ?
            SQL
            , [$id]
        );
    }
}

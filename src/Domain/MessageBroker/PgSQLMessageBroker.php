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

    const PROP_SERIAL = 'x-serial';

    private string $contentType;
    private string $queue;
    private array $options;
    private Runner $runner;
    private SerializerInterface $serializer;

    public function __construct(Runner $runner, SerializerInterface $serializer, array $options = [])
    {
        $this->contentType = $options['content_type'] ?? Property::DEFAULT_CONTENT_TYPE;
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
                            AND "consumed_at" IS NULL
                            AND ("retry_at" IS NULL OR "retry_at" <= current_timestamp)
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
                    "body"::bytea,
                    "retry_count"
                SQL,
                [$this->queue]
            )
            ->fetch()
        ;

        if ($data) {
            $serial = $data['serial'];

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

                // Restore necessary properties on which we are authoritative.
                $data['headers'][Property::MESSAGE_ID] = $data['id']->toString();
                $data['headers'][self::PROP_SERIAL] = $serial;
                if ($data['retry_count']) {
                    $data['headers'][Property::RETRY_COUNT] = (string)$data['retry_count'];
                }

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
                $this->markAsFailed($serial);

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
        $this->doDispatch($envelope);
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
        $serial = (int)$envelope->getProperty(self::PROP_SERIAL);

        if ($envelope->hasProperty(Property::RETRY_COUNT)) {
            // Having a count property means the caller did already set it,
            // we will not increment it ourself.
            $count = (int)$envelope->getProperty(Property::RETRY_COUNT, "0");
            $max = (int)$envelope->getProperty(Property::RETRY_MAX, (string)4);

            if ($count >= $max) {
                if ($serial) {
                    $this->markAsFailed($serial);
                }
                return; // Dead letter.
            }

            if (!$serial) {
                // This message was not originally delivered using this bus
                // so we cannot update an existing item in queue, create a
                // new one instead.
                // Note that this should be an error, it should not happen,
                // but re-queing it ourself is much more robust.
                $this->doDispatch($envelope, true);
                return;
            }

            $this->markForRetry($serial, $envelope);
            return;
        }

        if ($serial) {
            $this->markAsFailed($serial);
        }
    }

    /**
     * Real implementation of dispatch.
     */
    private function doDispatch(MessageEnvelope $envelope, bool $keepMessageIdIfPresent = false): void
    {
        if ($keepMessageIdIfPresent) {
            $messageId = $envelope->getMessageId();
            if (!$messageId) {
                $messageId = Uuid::uuid4()->toString();
            }
        } else {
            $messageId = Uuid::uuid4()->toString();
        }

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
     * Mark single message for retry.
     */
    private function markForRetry(int $serial, MessageEnvelope $envelope): void
    {
        $delay = (int)$envelope->getProperty(Property::RETRY_DELAI);
        $count = (int)$envelope->getProperty(Property::RETRY_COUNT, "0");

        $this->runner->execute(
            <<<SQL
            UPDATE "message_broker"
            SET
                "consumed_at" = null,
                "has_failed" = true,
                "headers" = ?::json,
                "retry_at" = current_timestamp + interval '"{$delay}" milliseconds',
                "retry_count" = GREATEST("retry_count" + 1, ?::int)
            WHERE
                "serial" = ?::int
            SQL
            , [
                $envelope->getProperties(),
                $count,
                $serial,
            ]
        );
    }

    /**
     * Mark single message as failed.
     */
    private function markAsFailed(int $serial): void
    {
        $this->runner->execute(
            <<<SQL
            UPDATE "message_broker"
            SET
                "has_failed" = true
            WHERE
                "serial" = ?
            SQL
            , [$serial]
        );
    }
}

<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\Messenger\Transport;

use Goat\Runner\Runner;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @todo implement purging messages
 */
final class PgSQLTransport implements TransportInterface
{
    private $debug = false;
    private $queue;
    private $options;
    private $runner;
    private $serializer;

    /**
     * Default constructor
     */
    public function __construct(Runner $runner, SerializerInterface $serializer, array $options = [], bool $debug = false)
    {
        $this->debug = $debug;
        $this->queue = $options['queue'] ?? 'default';
        $this->options = $options;
        $this->runner = $runner;
        $this->serializer = $serializer;
    }

    /**
     * Mark single message as failed.
     */
    private function markAsFailed(string $id)
    {
        $this->runner->execute(<<<SQL
update "message_broker"
set
    "has_failed" = true
where
    "id" = ?
SQL
            , [$id]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function get(): iterable
    {
        $data = $this->runner->execute(<<<SQL
update "message_broker"
set
    "consumed_at" = now()
where
    "id" in (
        select "id"
        from "message_broker"
        where
            "queue" = ?::string
            and "consumed_at" is null
        order by
            "created_at" asc
        limit 1 offset 0
    )
returning "id", "headers", "type", "content_type", "body"
SQL
       , [$this->queue])->fetch();


       if ($data) {
            try {
                if (\is_resource($data['body'])) { // Bytea
                    $data['body'] = \stream_get_contents($data['body']);
                }
                if (isset($data['type'])) {
                    $data['headers']['type'] = $data['type'];
                }
                if (isset($data['content_type'])) {
                    $data['headers']['Content-Type'] = $data['content_type'];
                }

                return [
                    $this
                        ->serializer
                        ->decode($data)
                        ->with(new TransportMessageIdStamp($data['id']))
                ];

            } catch (\Throwable $e) {
                $this->markAsFailed((string)$data['id']);

                throw new TransportException('Error while fetching messages', 0, $e);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function ack(Envelope $envelope): void
    {
        // Nothing to do, ACK was atomic in the UPDATE/RETURNING SQL query.
    }

    /**
     * {@inheritdoc}
     */
    public function reject(Envelope $envelope): void
    {
        // God I hate those stamps, message id should be a first class citizen
        // of the messenger API, not just an arbitrary stamp.
        // Same goes for content type, message type, and a few other common
        // properties.
        if ($stamp = $envelope->last(TransportMessageIdStamp::class)) {
            $this->markAsFailed((string)$stamp->getId());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function send(Envelope $envelope): Envelope
    {
        $data = $this->serializer->encode($envelope);

        $this->runner->execute(<<<SQL
insert into "message_broker"
    (id, queue, headers, type, content_type, body)
values
    (?::uuid, ?::string, ?::json, ?, ?, ?::bytea)
SQL
           , [
               Uuid::uuid4(),
               $this->queue,
               $data['headers'] ?? [],
               $data['headers']['type'] ?? null,
               $data['headers']['content_type'] ?? $data['headers']['content-type'] ?? $data['headers']['Content-Type'] ?? null,
               $data['body'],
           ]
        );

        return $envelope;
    }
}

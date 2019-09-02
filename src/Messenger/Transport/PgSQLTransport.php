<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\Messenger\Transport;

use Goat\Runner\Runner;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @todo implement purging messages
 * @todo this needs a serious rewrite for symfony >=4.3
 */
final class PgSQLTransport implements TransportInterface
{
    private $debug = false;
    private $queue;
    private $options;
    private $runner;
    private $serializer;
    private $shouldStop = false;

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
     * {@inheritdoc}
     */
    public function receive(callable $handler): void
    {
        $runner = $this->runner;
        $loopSleep = $this->options['loop_sleep'] ?? 200000;

        while (!$this->shouldStop) {

            $data = $runner->execute(<<<SQL
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

           if (!$data) {
                $handler(null);

                \usleep($loopSleep);
                if (\function_exists('pcntl_signal_dispatch')) {
                    \pcntl_signal_dispatch();
                }

                continue;
            }

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

                $handler($this->serializer->decode($data));

            } catch (\Throwable $e) {
                $data = $runner->execute(<<<SQL
update "message_broker"
set
    "has_failed" = true
where
    "id" = ?::bool
SQL
                , [$data['id']]);

                throw $e;
            } finally {
                if (\function_exists('pcntl_signal_dispatch')) {
                    \pcntl_signal_dispatch();
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        $this->shouldStop = true;
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
               $data['headers'],
               $data['headers']['type'] ?? null,
               $data['headers']['content_type'] ?? null,
               $data['body'],
           ]
        );

        return $envelope;
    }
}

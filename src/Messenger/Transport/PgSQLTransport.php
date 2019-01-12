<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\Messenger\Transport;

use Goat\Runner\Runner;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @todo implement purging messages
 */
final class PgSQLTransport implements TransportInterface
{
    private $debug = false;
    private $channel;
    private $options;
    private $runner;
    private $serializer;
    private $shouldStop = false;

    /**
     * Default constructor
     */
    public function __construct(Runner $runner, array $options = [], bool $debug = false)
    {
        $this->debug = $debug;
        $this->channel = $options['channel'] ?? 'default';
        $this->options = $options;
        $this->runner = $runner;
        $this->serializer = new DatabaseSerializer($options['signature'] ?? null, null, $this->debug);
    }

    private function normalizeHeaderName(string $string): string
    {
        return \str_replace(":", "", $this->stripEOL($string));
    }

    private function stripEOL(string $string): string
    {
        return \str_replace("\n", "\\n", $string);
    }

    private function serializeHeaders(array $headers): string
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[] = sprintf("%s: %s", $this->normalizeHeaderName($key), $this->stripEOL($value));
        }
        return \implode("\n", $normalized);
    }

    private function unserializeHeaders(string $headers): array
    {
        $ret = [];
        foreach (\explode("\n", $headers) as $string) {
            list($key, $value) = \explode(':', $string, 2);
            $ret[\trim($key)] = \trim($value);
        }
        return $ret;
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
            "channel" = ?
            and "consumed_at" is null
        order by
            "created_at" asc
        limit 1 offset 0
    )
returning "id", "headers", "body"
SQL
           , [$this->channel])->fetch();

           if (!$data) {
                $handler(null);

                \usleep($loopSleep);
                if (\function_exists('pcntl_signal_dispatch')) {
                    \pcntl_signal_dispatch();
                }

                continue;
            }

            try {
                $data['headers'] = $this->unserializeHeaders($data['headers'] ?? []);
                if (\is_resource($data['body'])) { // Bytea
                    $data['body'] = \stream_get_contents($data['body']);
                }

                $handler($this->serializer->decode($data));

            } catch (\Throwable $e) {
                $data = $runner->execute(<<<SQL
update "message_broker"
set
    "has_failed" = true
where
    "id" = ?
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
    (channel, headers, body)
values
    (?, ?, ?)
SQL
           , [
               $this->channel,
               $this->serializeHeaders($data['headers']),
               $data['body'],
           ]
        );

        return $envelope;
    }
}

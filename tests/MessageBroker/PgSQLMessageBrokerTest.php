<?php

declare(strict_types=1);

namespace Goat\Tests\MessageBroker;

use Goat\MessageBroker\MessageBroker;
use Goat\MessageBroker\PgSQLMessageBroker;
use Goat\Runner\Runner;

final class PgSQLMessageBrokerTest extends AbstractMessageBrokerTest
{
    /**
     * {@inheritdoc}
     *
     * Override this for your own event store.
     */
    protected function createTestSchema(Runner $runner, string $schema)
    {
        $runner->execute(
            <<<SQL
            DROP TABLE IF EXISTS "{$schema}"."message_broker"
            SQL
        );

        $runner->execute(
            <<<SQL
            CREATE TABLE "{$schema}"."message_broker" (
                "id" uuid NOT NULL,
                "serial" serial NOT NULL,
                "queue" varchar(500) NOT NULL DEFAULT 'default',
                "created_at" timestamp NOT NULL DEFAULT now(),
                "consumed_at" timestamp DEFAULT NULL,
                "has_failed" bool DEFAULT false,
                "headers" jsonb NOT NULL DEFAULT '{}'::jsonb,
                "type" text DEFAULT NULL,
                "content_type" varchar(500) DEFAULT NULL,
                "body" bytea NOT NULL,
                "retry_count" bigint DEFAULT 0,
                "retry_at" timestamp DEFAULT NULL,
                "error_code" bigint default null,
                "error_message" varchar(500) default null,
                "error_trace" text default null,
                PRIMARY KEY ("serial")
            );
            SQL
        );

        $runner->execute(
            <<<SQL
            DROP TABLE IF EXISTS "{$schema}"."message_broker_dead_letters"
            SQL
        );

        $runner->execute(
            <<<SQL
            CREATE TABLE "{$schema}"."message_broker_dead_letters" (
                "id" uuid NOT NULL,
                "serial" bigint,
                "queue" varchar(500) NOT NULL DEFAULT 'default',
                "created_at" timestamp NOT NULL DEFAULT now(),
                "consumed_at" timestamp DEFAULT NULL,
                "headers" jsonb NOT NULL DEFAULT '{}'::jsonb,
                "type" text DEFAULT NULL,
                "content_type" varchar(500) DEFAULT NULL,
                "body" bytea NOT NULL,
                "error_code" bigint default null,
                "error_message" varchar(500) default null,
                "error_trace" text default null,
                PRIMARY KEY ("id")
            );
            SQL
        );
    }

    /**
     * Create your own event store
     *
     * Override this for your own event store.
     */
    protected function createMessageBroker(Runner $runner, string $schema): MessageBroker
    {
        $this->createTestSchema($runner, $schema);

        return new PgSQLMessageBroker($runner, $this->createSerializer());
    }
}

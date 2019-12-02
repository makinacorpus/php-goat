<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\EventStore;

use Goat\Domain\EventStore\Event;
use Goat\Domain\EventStore\EventStore;
use Goat\Domain\EventStore\EventStream;
use Goat\Domain\EventStore\Goat\GoatEventStore;
use Goat\Domain\Serializer\UuidNormalizer;
use Goat\Runner\Runner;
use Goat\Runner\Testing\DatabaseAwareQueryTest;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

abstract class AbstractEventStoreTest extends DatabaseAwareQueryTest
{
    /**
     * {@inheritdoc}
     *
     * Override this for your own event store.
     */
    protected function getSupportedDrivers(): ?array
    {
        return ['pgsql'];
    }

    /**
     * {@inheritdoc}
     *
     * Override this for your own event store.
     */
    protected function createTestSchema(Runner $runner)
    {
        $runner->execute(<<<SQL
create table if not exists "event_index" (
    "aggregate_id" uuid not null,
    "aggregate_type" varchar(500) not null default 'none',
    "aggregate_root" uuid default null,
    "namespace" varchar(500) default 'default',
    "created_at" timestamp not null default now(),
    primary key("aggregate_id"),
    foreign key ("aggregate_root") references "event_index" ("aggregate_id") on delete restrict
);
SQL
        );

        $runner->execute(<<<SQL
create table if not exists "event_default" (
    "position" bigserial not null,
    "aggregate_id" uuid not null,
    "revision" integer not null,
    "created_at" timestamp not null default now(),
    "name" varchar(500) not null,
    "properties" jsonb default '{}'::jsonb,
    "data" bytea not null,
    "has_failed" bool not null default false,
    "error_code" bigint default null,
    "error_message" varchar(500) default null,
    "error_trace" text default null,
    primary key("position"),
    unique ("aggregate_id", "revision"),
    foreign key ("aggregate_id") references "event_index" ("aggregate_id") on delete restrict
);
SQL
        );

        $runner->execute("delete from event_default;");
        $runner->execute("delete from event_index;");
    }

    /**
     * Create your own event store
     *
     * Override this for your own event store.
     */
    protected function createEventStore(Runner $runner): EventStore
    {
        $this->prepare($runner);

        $store = new GoatEventStore($runner);
        $store->setSerializer($this->createSerializer());

        return $store;
    }

    /**
     * Create a new UUIDv4
     */
    final protected function createUuid(): UuidInterface
    {
        return Uuid::uuid4();
    }

    /**
     * Create Symfony serializer
     */
    final protected function createSerializer(): SerializerInterface
    {
        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizers = [new ArrayDenormalizer(), new UuidNormalizer(), new ObjectNormalizer()];

        return new Serializer($normalizers, $encoders);
    }

    /**
     * Find events of
     */
    final protected function findEventOf(EventStore $eventStore, UuidInterface $id): EventStream
    {
        return $eventStore->query()->for($id)->failed(null)->reverse(true)->execute();
    }

    /**
     * Find latest event of
     */
    final protected function findLastEventOf(EventStore $eventStore, UuidInterface $id): ?Event
    {
        $stream = $eventStore->query()->for($id)->failed(null)->reverse(true)->limit(1)->execute();
        foreach ($stream as $event) {
            return $event;
        }
        return null;
    }
}

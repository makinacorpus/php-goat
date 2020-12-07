<?php

declare(strict_types=1);

namespace Goat\Preferences\Tests;

use Goat\Runner\Testing\DatabaseAwareQueryTest;
use Goat\Preferences\Domain\Repository\GoatPreferencesRepository;

final class GoatRepositoryTest extends DatabaseAwareQueryTest
{
    use RepositoryTestTrait;

    private $runner;

    /**
     * {@inheritdoc}
     */
    protected function getSupportedDrivers(): ?array
    {
        return ['pgsql'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getRepositories(): iterable
    {
        foreach ($this->runnerDataProvider() as $args) {
            $runner = \reset($args)->getRunner();

            $runner->execute(
                <<<SQL
                create table if not exists "preferences" (
                    "name" varchar(500) not null,
                    "created_at" timestamp not null default current_timestamp,
                    "updated_at" timestamp not null default current_timestamp,
                    "type" varchar(500) default null,
                    "is_collection" bool not null default false,
                    "is_hashmap" bool not null default false,
                    "is_serialized" bool not null default false,
                    "value" text,
                    primary key ("name")
                );
                SQL
            );

            yield new GoatPreferencesRepository($runner);
        }
    }
}

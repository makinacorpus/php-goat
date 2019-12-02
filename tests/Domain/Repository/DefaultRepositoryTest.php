<?php

declare(strict_types=1);

namespace Goat\Domain\Tests\Repository;

use Goat\Domain\Repository\DefaultRepository;
use Goat\Domain\Repository\GoatRepositoryInterface;
use Goat\Domain\Repository\WritableDefaultRepository;
use Goat\Domain\Repository\WritableRepositoryInterface;
use Goat\Runner\Runner;

class DefaultRepositoryTest extends AbstractRepositoryTest
{
    /**
     * {@inheritdoc}
     */
    protected function supportsJoin(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function createRepository(Runner $driver, string $class, array $primaryKey): GoatRepositoryInterface
    {
        $this->markTestSkipped();

        return new DefaultRepository($driver, $class, $primaryKey, 'some_entity', 't', ['id', 'id_user', 'status', 'foo', 'bar', 'baz']);
    }

    /**
     * {@inheritdoc}
     */
    protected function createWritableRepository(Runner $driver, string $class, array $primaryKey): WritableRepositoryInterface
    {
        $this->markTestSkipped();

        return new WritableDefaultRepository($driver, $class, $primaryKey, 'some_entity', 't', ['id', 'id_user', 'status', 'foo', 'bar', 'baz']);
    }
}

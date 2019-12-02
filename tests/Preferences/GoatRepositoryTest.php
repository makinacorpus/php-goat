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
        foreach ($this->getRunners() as $data) {
            yield new GoatPreferencesRepository(\reset($data));
        }
    }
}

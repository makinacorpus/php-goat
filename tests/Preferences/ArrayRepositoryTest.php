<?php

declare(strict_types=1);

namespace Goat\Preferences\Tests;

use Goat\Preferences\Domain\Repository\ArrayPreferencesRepository;
use PHPUnit\Framework\TestCase;

final class ArrayRepositoryTest extends TestCase
{
    use RepositoryTestTrait;

    /**
     * {@inheritdoc}
     */
    protected function getRepositories(): iterable
    {
        return [new ArrayPreferencesRepository()];
    }
}

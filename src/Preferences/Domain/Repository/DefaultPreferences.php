<?php

declare(strict_types=1);

namespace Goat\Preferences\Domain\Repository;

/**
 * Preference reader implementation.
 *
 * Caching will be implemented around this class.
 */
final class DefaultPreferences implements Preferences
{
    private PreferencesRepository $repository;
    private ?PreferencesSchema $schema = null;

    public function __construct(PreferencesRepository $repository, ?PreferencesSchema $schema = null)
    {
        $this->repository = $repository;
        $this->schema = $schema;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name)
    {
        $value = $this->repository->get($name);

        if (null === $value && $this->schema) {
            return $this->schema->getDefault($name);
        }

        return $value;
    }
}

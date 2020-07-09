<?php

declare(strict_types=1);

namespace Goat\Preferences\Domain\Handler;

use Goat\Dispatcher\Handler;
use Goat\Preferences\Domain\Message\PreferenceValueDelete;
use Goat\Preferences\Domain\Message\PreferenceValueSet;
use Goat\Preferences\Domain\Message\PreferenceValueSetMany;
use Goat\Preferences\Domain\Model\ValueValidator;
use Goat\Preferences\Domain\Repository\PreferencesRepository;
use Goat\Preferences\Domain\Repository\PreferencesSchema;

/**
 * Handle preference set messages when plugged over the symfony messenger.
 */
final class PreferencesHandler implements Handler
{
    private PreferencesRepository $repository;
    private ?PreferencesSchema $schema = null;

    /**
     * Default constructor
     */
    public function __construct(PreferencesRepository $repository, ?PreferencesSchema $schema = null)
    {
        $this->repository = $repository;
        $this->schema = $schema;
    }

    /**
     * Validate value, return save callback.
     */
    private function handleValue(string $name, $value): callable
    {
        if ($this->schema) {
            $schema = $this->schema->getType($name);
            $value = ValueValidator::validate($schema, $value);
        } else {
            $schema = ValueValidator::getTypeOf($value);
            $value = ValueValidator::validate($schema, $value);
        }

        return function () use ($name, $value, $schema) {
            $this->repository->set($name, $value, $schema);
        };
    }

    /**
     * Handler
     */
    public function doSet(PreferenceValueSet $command): void
    {
        ($this->handleValue($command->getName(), $command->getValue()))();
    }

    /**
     * Handler
     */
    public function doSetMany(PreferenceValueSetMany $command): void
    {
        $callables = [];

        // Pre-validate everything, to ensure we won't store anything if any
        // value fails validation.
        foreach ($command->getValueList() as $name => $value) {
            $callables[] = $this->handleValue($name, $value);
        }

        // No error here means everything is valid, store everything.
        foreach ($callables as $callback) {
            $callback();
        }
    }

    /**
     * Handler
     */
    public function doDelete(PreferenceValueDelete $command): void
    {
        $this->repository->delete($command->getName());
    }
}

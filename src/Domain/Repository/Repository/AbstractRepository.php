<?php

declare(strict_types=1);

namespace Goat\Domain\Repository\Repository;

use Goat\Domain\Repository\RepositoryInterface;
use Goat\Domain\Repository\Definition\DefinitionLoader;
use Goat\Domain\Repository\Definition\RepositoryDefinition;
use Goat\Domain\Repository\Registry\RepositoryRegistryAware;
use Goat\Domain\Repository\Registry\RepositoryRegistryAwareTrait;
use Goat\Query\ExpressionRelation;

/**
 * Repository definition logic.
 *
 * You must choose between either:
 *   - implement runtime methods, all those prefixed with "define",
 *   - use annotations or attributes.
 *
 * Remember that as a soon as you implement the defineClass() method,
 * annotations and attributes methods will be ignored.
 *
 * This code mostly exists for backward compatibility, officially supported
 * way of definining your repositories is by using annotations or attributes.
 *
 * Attributes are highly recommended, doctrine/annotations suffers from some
 * limitations we cannot work around, which reduces the functionnaly surface.
 */
abstract class AbstractRepository implements RepositoryInterface, RepositoryRegistryAware
{
    use RepositoryRegistryAwareTrait;

    private ?RepositoryDefinition $repositoryDefinition = null;

    /**
     * Get this repository definition.
     */
    public function getRepositoryDefinition(): RepositoryDefinition
    {
        return $this->repositoryDefinition ?? ($this->repositoryDefinition = $this->buildDefinition());
    }

    /**
     * Inject preloaded definition for production runtime scenario.
     */
    public function setRepositoryDefinition(RepositoryDefinition $repositoryDefinition): void
    {
        if ($this->repositoryDefinition) {
            throw new \LogicException("Repository definition was already set before initialization.");
        }
        $this->repositoryDefinition = $repositoryDefinition;
    }

    /**
     * Runtime build definition when no definition was injected.
     */
    protected function buildDefinition(): RepositoryDefinition
    {
        // Otherwise, this should not happen on a production environment but
        // we are going to spawn a definition loader and do everything that
        // otherwise a cache warmup pass would do.
        return (new DefinitionLoader())->loadDefinition(static::class);
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated
     *   Please use getRepositoryDefinition() instead.
     */
    public final function getClassName(): string
    {
        // @\trigger_error(\sprintf("You should use %s::getRepositoryDefinition() instead.", static::class), E_USER_DEPRECATED);

        return $this->getRepositoryDefinition()->getEntityClassName();
    }

    /**
     * @deprecated
     *   Will be removed soon.
     */
    public function getRelation(): ExpressionRelation
    {
        return $this->getTable();
    }

    /**
     * Get table for queries.
     */
    public function getTable(): ExpressionRelation
    {
        $table = $this->getRepositoryDefinition()->getTableName();

        return ExpressionRelation::create($table->getName(), $table->getAlias(), $table->getSchema());
    }
}

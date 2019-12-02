<?php

declare(strict_types=1);

namespace Goat\Domain\Repository;

use Goat\Query\DeleteQuery;
use Goat\Query\InsertQueryQuery;
use Goat\Query\InsertValuesQuery;
use Goat\Query\UpdateQuery;

/**
 * Add update and insert functions to repositories.
 *
 * Be aware that this can only write on a single relation at once.
 */
interface WritableRepositoryInterface extends RepositoryInterface
{
    /**
     * Create one entity from values
     *
     * @param array $values
     *   Values for the entity
     *
     * @return mixed
     *   The created entity
     */
    public function create(array $values);

    /**
     * Update one entity from another instance values, ideal for form with mapping
     *
     * @todo handle gracefully null values
     *
     * @param mixed $entity
     *   The entity to duplicate for fields
     *
     * @return mixed
     *   The created entity
     * @param mixed[] $values
     *   Additional values to override the provided entity ones
     *
     * @throws EntityNotFoundError
     *   If the entity does not exists
     */
    public function createFrom($entity, array $values = []);

    /**
     * Update one entity with values
     *
     * @param int|string|int[]|string[] $id
     *   Primary key
     * @param bool $throwIfNotExists
     *   Throw exception when entity does not exists
     *
     * @return mixed
     *   The updated entity
     *
     * @throws EntityNotFoundError
     *   If the entity does not exists and $throwIfNotExists is true
     */
    public function delete($id, bool $raiseErrorOnMissing = false);

    /**
     * Update one entity with values
     *
     * @param int|string|int[]|string[] $id
     *   Primary key
     * @param mixed[] $values
     *   New values to set, can be partial
     *
     * @return mixed
     *   The updated entity
     *
     * @throws EntityNotFoundError
     *   If the entity does not exists
     */
    public function update($id, array $values);

    /**
     * Update one entity from another instance values, ideal for form with mapping
     *
     * @todo handle gracefully null values
     *
     * @param int|string|int[]|string[] $id
     *   Primary key
     * @param mixed $entity
     *   The entity to duplicate for fields
     * @param mixed[] $values
     *   Additional values to override the provided entity ones
     *
     * @return mixed
     *   The updated entity
     *
     * @throws EntityNotFoundError
     *   If the entity does not exists
     */
    public function updateFrom($id, $entity, array $values = []);

    /**
     * Create update query
     *
     * @param mixed $criteria
     *
     * @see \Goat\Domain\Repository\DefaultRepository::expandCriteria()
     *   For $criteria parameter definition
     *
     * @return UpdateQuery
     */
    public function createUpdate($criteria = null): UpdateQuery;

    /**
     * Create delete query
     *
     * @param mixed $criteria
     *
     * @see \Goat\Domain\Repository\DefaultRepository::expandCriteria()
     *   For $criteria parameter definition
     *
     * @return UpdateQuery
     */
    public function createDelete($criteria = null): DeleteQuery;

    /**
     * Create insert query with values
     *
     * @return InsertValuesQuery
     */
    public function createInsertValues(): InsertValuesQuery;

    /**
     * Create insert query from query
     *
     * @return InsertQueryQuery
     */
    public function createInsertQuery(): InsertQueryQuery;
}

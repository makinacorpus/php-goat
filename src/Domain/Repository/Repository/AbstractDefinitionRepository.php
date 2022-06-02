<?php

declare(strict_types=1);

namespace Goat\Domain\Repository\Repository;

use Goat\Domain\Repository\Key;
use Goat\Domain\Repository\Definition\DatabaseColumn;
use Goat\Domain\Repository\Definition\DatabasePrimaryKey;
use Goat\Domain\Repository\Definition\DatabaseSelectColumn;
use Goat\Domain\Repository\Definition\DatabaseTable;
use Goat\Domain\Repository\Definition\RepositoryDefinition;
use Goat\Domain\Repository\Definition\RepositoryDefinitionBuilder;

/**
 * Legacy repository definition logic.
 *
 * Remember that as a soon as you implement the defineClass() method,
 * annotations and attributes methods will be ignored.
 *
 * This code mostly exists for backward compatibility, officially supported
 * way of definining your repositories is by using annotations or attributes.
 *
 * Attributes are highly recommended, doctrine/annotations suffers from some
 * limitations we cannot work around, which reduces the functionnaly surface.
 *
 * @deprecated
 *   This class only exists for backward compatibility and will be removed
 *   in a future version, please use attributes for defining your repository
 *   instead.
 */
abstract class AbstractDefinitionRepository extends AbstractRepository
{
    /**
     * Runtime build definition when no definition was injected.
     */
    protected function buildDefinition(): RepositoryDefinition
    {
        @\trigger_error("Using attributes is the only supported way to define repository.", E_USER_DEPRECATED);

        if ($entityClassName = $this->defineClass()) {
            // This repository is auto-defined. This means that this will be
            // executed at runtime.
            $definitionBuilder = new RepositoryDefinitionBuilder();
            $definitionBuilder->setEntityClassName($entityClassName);

            $primaryKey = $this->definePrimaryKey();
            if (null !== $primaryKey) {
                if ($primaryKey instanceof DatabasePrimaryKey) {
                    $definitionBuilder->setDatabasePrimaryKey($primaryKey->getPrimaryKey());
                } else if (\is_array($primaryKey) || \is_string($primaryKey)) {
                    $definitionBuilder->setDatabasePrimaryKey(new Key($primaryKey));
                } else {
                    throw new \LogicException(\sprintf("%s::definePrimaryKey() must return null, a string, string[] or a %s instance.", DatabasePrimaryKey::class));
                }
            }

            $table = $this->defineTable();
            if (null !== $table) {
                if (!$table instanceof DatabaseTable) {
                    if (\is_string($table)) {
                        $table = new DatabaseTable($table);
                    } else {
                        throw new \LogicException(\sprintf("%s::defineTable() must return null, a string or a %s instance.", DatabaseTable::class));
                    }
                }
                $definitionBuilder->setTableName($table);
            }

            foreach ($this->defineColumns() as $alias => $column) {
                if (\is_int($alias)) {
                    $definitionBuilder->addDatabaseColumns(new DatabaseColumn($column));
                } else {
                    $definitionBuilder->addDatabaseColumns(new DatabaseColumn($column, $alias));
                }
            }

            foreach ($this->defineSelectColumns() as $alias => $column) {
                if (\is_int($alias)) {
                    $definitionBuilder->addDatabaseSelectColumns(new DatabaseSelectColumn($column));
                } else {
                    if (\is_object($column)) {
                        // @todo
                        //   This can be ExpressionRaw instances, case in which
                        //   we probably need to convert it to something else,
                        //   such as a DatabaseExpression maybe?
                    } else {
                        $definitionBuilder->addDatabaseSelectColumns(new DatabaseSelectColumn($column, $alias));
                    }
                }
            }

            return $definitionBuilder->build();
        }

        // Otherwise, this should not happen on a production environment but
        // we are going to spawn a definition loader and do everything that
        // otherwise a cache warmup pass would do.
        return parent::buildDefinition();
    }

    /**
     * Define class that will be hydrated by this repository, if none given raw
     * database result will be returned by load/find methods.
     */
    protected function defineClass(): ?string
    {
        return null;
    }

    /**
     * Define table.
     *
     * @return null|string|DatabaseTable
     */
    protected function defineTable()
    {
        return null;
    }

    /**
     * Define primary key columns.
     *
     * @return null|string|string[]|DatabasePrimaryKey
     */
    protected function definePrimaryKey()
    {
        return null;
    }

    /**
     * Define columns you wish to map onto the relation in select, update
     * and insert queries.
     *
     * Keys will always be ignored, don't set keys here.
     *
     * Override this method in your implementation to benefit from this. If not
     * overriden, per default select queries will select "RELATION_ALIAS.*".
     *
     * If defineSelectColumns() is defined, this method will NOT be used for
     * select queries.
     *
     * @return string[]
     *   Keys are column aliases, values are SQL column expressions.
     */
    protected function defineColumns(): array
    {
        return [];
    }

    /**
     * Same as defineColumns() but it will only append those columns into the
     * select queries, and won't impact the update and insert queries.
     *
     * Keys of the returned array are column aliases, if you don't want to
     * alias one or more columns, just let a numeric index for those. Values
     * are column names, that can be formatted with a table alias as prefix
     * in the form "RELATION_ALIAS.COLUMN_NAME", in case your repository uses
     * join with multiple tables/relations.
     *
     * Override this method in your implementation to benefit from this. If not
     * overriden, per default select queries will select "RELATION_ALIAS.*".
     *
     * If this is defined, defineColumns() method will NOT be used for select
     * queries.
     *
     * @return string[]
     *   Keys are column aliases, values are SQL column expressions.
     */
    protected function defineSelectColumns(): array
    {
        return [];
    }

    /**
     * This gives you a performance boost by forcing column type that will be
     * propagated to the result iterator, which will not need to query the
     * backend provided metadata to guess data types. This is performance boost
     * seems exclusive to PDO, pgsql extension gives that information for free.
     *
     * @return string[]
     *   Keys are either column names or column alias, in all cases, it must
     *   match the result aliased column names.
     */
    protected function defineSelectColumnsTypes(): array
    {
        return [];
    }
}

<?php

declare(strict_types=1);

namespace Goat\Domain\Repository;

use Goat\Domain\Repository\Definition\DatabaseColumn;
use Goat\Domain\Repository\Definition\DatabasePrimaryKey;
use Goat\Domain\Repository\Definition\DatabaseTable;
use Goat\Domain\Repository\Hydration\ResultRow;
use Goat\Domain\Repository\Repository\AbstractDefinitionRepository;
use Goat\Domain\Repository\Result\GoatQueryRepositoryResult;
use Goat\Driver\Runner\AbstractRunner;
use Goat\Query\DeleteQuery;
use Goat\Query\Expression;
use Goat\Query\ExpressionColumn;
use Goat\Query\ExpressionRelation;
use Goat\Query\InsertQueryQuery;
use Goat\Query\InsertValuesQuery;
use Goat\Query\MergeQuery;
use Goat\Query\Query;
use Goat\Query\QueryError;
use Goat\Query\SelectQuery;
use Goat\Query\UpdateQuery;
use Goat\Query\UpsertQueryQuery;
use Goat\Query\UpsertValuesQuery;
use Goat\Query\Where;
use Goat\Runner\Runner;
use Goat\Runner\Hydrator\HydratorRegistry;
use Goat\Domain\Repository\Collection\Collection;
use Goat\Domain\Repository\Collection\ArrayCollection;

/**
 * Table repository is a simple model implementation that works on an arbitrary
 * select query.
 */
class DefaultRepository extends AbstractDefinitionRepository
{
    protected Runner $runner;

    private ?DatabasePrimaryKey $userDefinedPrimaryKey = null;
    private ?DatabaseTable $userDefinedTable = null;

    /**
     * Default constructor
     *
     * @param Runner $runner
     * @param string $class
     *   Class that will be hydrated
     * @param string[] $primaryKey
     *   Column names that is the primary key
     * @param string|ExpressionRelation $relation
     *   Relation, if a string, no schema nor alias will be used
     */
    public function __construct(Runner $runner, ?array $primaryKey = null, $table = null, ?string $tableAlias = null)
    {
        $this->runner = $runner;

        $deprecationEmitted = false;

        if (null !== $primaryKey) {
            if (!$deprecationEmitted) {
                @\trigger_error("Using attributes is the only supported way to define repository.", E_USER_DEPRECATED);
                $deprecationEmitted = true;
            }

            $this->userDefinedPrimaryKey = new DatabasePrimaryKey($primaryKey);
        }

        if (null !== $table) {
            if (!$deprecationEmitted) {
                @\trigger_error("Using attributes is the only supported way to define repository.", E_USER_DEPRECATED);
                $deprecationEmitted = true;
            }

            if ($table instanceof ExpressionRelation) {
                $this->userDefinedTable = new DatabaseTable($table->getName(), $tableAlias ?? $table->getAlias(), $table->getSchema());
            } else {
                $this->userDefinedTable = new DatabaseTable($table, $tableAlias);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function defineTable()
    {
        return $this->userDefinedTable;
    }

    /**
     * {@inheritdoc}
     */
    protected function definePrimaryKey()
    {
        return $this->userDefinedPrimaryKey;
    }

    /**
     * Map references collections on the hydrated objects.
     *
     * This allows reference collections lazy-loading.
     *
     * @return string[]|callable[]
     *   Values are either string values, which must be existring public
     *   method names on this repository instance, or callable instances.
     *   Callables should take a single ResultRow argument, which is a
     *   container of being hydrated values.
     *   Keys are property names on which the callback result will be
     *   set.
     */
    protected function defineLazyCollectionMapping(): array
    {
        return [];
    }

    /**
     * Allow the repository to map method results on hydrated object properties.
     *
     * This is the very same that defineLazyCollectionMapping() except it will
     * return LazyProperty instances instead.
     *
     * Read defineLazyCollectionMapping() for documentation.
     *
     * Only difference is that the domain object that will inherit from this
     * property will need to call LazyProperty::unwrap() programmatically to
     * fetch the result.
     *
     * @return string|]|callable[]
     */
    protected function defineLazyPropertyMapping(): array
    {
        return [];
    }

    /**
     * Expand primary key item
     *
     * @param mixed $values
     */
    protected final function expandPrimaryKey($values): Where
    {
        $definition = $this->getRepositoryDefinition();

        if (!$definition->hasDatabasePrimaryKey()) {
            throw new QueryError("Repository has no primary key defined.");
        }

        $primaryKey = $definition->getDatabasePrimaryKey();

        return $this->expandKey($primaryKey, $values, $this->getTable()->getAlias());
    }

    /**
     * Expand key.
     *
     * @param mixed $values
     */
    protected final function expandKey(Key $key, $values, ?string $tableAlias = null): Where
    {
        if (!$tableAlias) {
            $tableAlias = $this->getTable()->getAlias();
        }
        if (!$values instanceof KeyValue) {
            $values = $key->expandWith($values);
        }

        $ret = new Where();
        foreach (\array_combine($key->getColumnNames(), $values->all()) as $column => $value) {
            // Repository can choose to actually already have prefixed the column
            // primary key using the alias, let's cover this use case too: this
            // might happen if either the original select query do need
            // deambiguation from the start, or if the API user was extra
            // precautionous.
            if (false === \strpos($column, '.')) {
                $ret->condition(ExpressionColumn::create($column, $tableAlias), $value);
            } else {
                $ret->condition(ExpressionColumn::create($column), $value);
            }
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public final function getRunner(): Runner
    {
        return $this->runner;
    }

    /**
     * Normalize column for select
     */
    private final function normalizeColumn($column, ?string $relationAlias = null): Expression
    {
        if ($column instanceof Expression) {
            return $column;
        }
        if (false === \strpos($column, '.')) {
            return new ExpressionColumn($column, $relationAlias);
        }
        return new ExpressionColumn($column);
    }

    /**
     * Can query hold a returning statement.
     */
    private final function checkIsEligibleToReturning(Query $query): void
    {
        if (!$query instanceof InsertQueryQuery &&
            !$query instanceof InsertValuesQuery &&
            !$query instanceof UpsertQueryQuery &&
            !$query instanceof UpsertValuesQuery &&
            !$query instanceof MergeQuery &&
            !$query instanceof DeleteQuery &&
            !$query instanceof UpdateQuery
        ) {
            throw new QueryError("Query cannot hold a RETURNING clause.");
        }
    }

    /**
     * Add relation columns to select.
     */
    protected function configureQueryForHydrationViaReturning(Query $query, ?string $tableAlias = null): void
    {
        $this->checkIsEligibleToReturning($query);

        if (!$tableAlias) {
            $table = $this->getTable();
            $tableAlias = $table->getAlias() ?? $table->getName();
        }

        $some = false;
        $definition = $this->getRepositoryDefinition();
        if ($definition->hasDatabaseColumns()) {
            $some = true;
            foreach ($definition->getDatabaseColumns() as $column) {
                \assert($column instanceof DatabaseColumn);
                $columnTableAlias = $column->getTableName() ?? $tableAlias;
                if ($columnTableAlias === $tableAlias) { // We do not support JOIN on returning, yet.
                    $query->returning(new ExpressionColumn($column->getColumnName(), $tableAlias), $column->getPropertyName());
                }
            }
        }
        if ($definition->hasDatabaseSelectColumns()) {
            $some = true;
            foreach ($definition->getDatabaseSelectColumns() as $column) {
                \assert($column instanceof DatabaseColumn);
                $columnTableAlias = $column->getTableName() ?? $tableAlias;
                if ($columnTableAlias === $tableAlias) { // We do not support JOIN on returning, yet.
                    $query->returning(new ExpressionColumn($column->getColumnName(), $tableAlias), $column->getPropertyName());
                }
            }
        }
        if (!$some) {
            $query->returning(new ExpressionColumn('*', $tableAlias));
        }

        $query->setOption('hydrator', $this->getHydrator());
        $query->setOption('types', $this->defineSelectColumnsTypes());
    }

    /**
     * Add relation columns to select.
     */
    protected function configureQueryForHydrationViaSelect(SelectQuery $select, ?string $tableAlias = null): void
    {
        if (!$tableAlias) {
            $table = $this->getTable();
            $tableAlias = $table->getAlias() ?? $table->getName();
        }

        $some = false;
        $definition = $this->getRepositoryDefinition();
        if ($definition->hasDatabaseColumns()) {
            $some = true;
            foreach ($definition->getDatabaseColumns() as $column) {
                \assert($column instanceof DatabaseColumn);
                $columnTableAlias = $column->getTableName() ?? $tableAlias;
                $select->column(new ExpressionColumn($column->getColumnName(), $columnTableAlias), $column->getPropertyName());
            }
        }
        if ($definition->hasDatabaseSelectColumns()) {
            $some = true;
            foreach ($definition->getDatabaseSelectColumns() as $column) {
                \assert($column instanceof DatabaseColumn);
                $columnTableAlias = $column->getTableName() ?? $tableAlias;
                $select->column(new ExpressionColumn($column->getColumnName(), $columnTableAlias), $column->getPropertyName());
            }
        }
        if (!$some) {
            $select->column(new ExpressionColumn('*', $tableAlias));
        }

        $select->setOption('hydrator', $this->getHydrator());
        $select->setOption('types', $this->defineSelectColumnsTypes());
    }

    /**
     * {@inheritdoc}
     */
    public function createSelect($criteria = null, bool $withColumns = true): SelectQuery
    {
        $table = $this->getTable();
        $select = $this->getRunner()->getQueryBuilder()->select($table);

        if ($withColumns) {
            $this->configureQueryForHydrationViaSelect($select, $table->getAlias() ?? $table->getName());
        }

        if ($criteria) {
            $select->where(RepositoryQuery::expandCriteria($criteria));
        }

        return $select;
    }

    /**
     * From a callback that is supposed to use a ResultRow instance as only
     * parameter, wrap it if necessary to allow usage of a bare array instead
     * for backward compatibility.
     */
    private function userDefinedNormalizerNormalize(callable $callback): callable
    {
        if (!$callback instanceof \Closure) {
            $callback = \Closure::fromCallable($callback);
        }

        $refFunc = new \ReflectionFunction($callback);

        $normParamCount = 0;
        foreach ($refFunc->getParameters() as $parameter) {
            \assert($parameter instanceof \ReflectionParameter);

            if ($normParamCount) {
                throw new \InvalidArgumentException("Normalizer callback can have only one parameter.");
            }
            $normParamCount++;

            // Check parameter type.
            if (!$parameter->hasType() || !($refType = $parameter->getType()) instanceof \ReflectionNamedType || ResultRow::class !== $refType->getName()) {
                @\trigger_error(\sprintf("Using result row as array is deprecated, please type and use your normalizer parameter with the %s interface.", __CLASS__), E_USER_DEPRECATED);

                return fn (ResultRow $row) => $row->apply($callback);
            }
        }

        if (!$normParamCount) {
            throw new \InvalidArgumentException("Normalizer callback from repository has no parameters.");
        }

        return $callback;
    }

    /**
     * Attempt to normalize user given lazy property hydrator.
     *
     * Legacy code can return many things.
     *
     * First, if the user returns a LazyProperty object, we will wrap the user
     * callback to get the primary key value as first parameter, which is the
     * legacy behaviour, then emit a deprecation notice.
     *
     * Then, we check if the callback expect a ResultRow parameter, case in
     * which we do not modify it and return it as-is. In the opposite case,
     * we will consider the callback expects the primary key value as well,
     * and wrap the callback to use it.
     */
    private function userDefinedLazyHydratorNormalize(callable $callback): callable
    {
        if (!$callback instanceof \Closure) {
            $callback = \Closure::fromCallable($callback);
        }

        $refFunc = new \ReflectionFunction($callback);
        $refParams = $refFunc->getParameters();
        $refParamsCount = \count($refParams);
        $refTypeName = null;

        if (0 === $refParamsCount) {
            return $callback;
        }
        if (1 !== $refParamsCount) {
            throw new \InvalidArgumentException("User-defined lazy hydrators callback can have only one parameter.");
        }

        $parameter = $refParams[0];
        \assert($parameter instanceof \ReflectionParameter);

        if ($parameter->hasType()) {
            $refType = $parameter->getType();
            if (!$refType instanceof \ReflectionNamedType) {
                throw new \InvalidArgumentException("User-defined lazy hydrators callback first parameter must be a single named type.");
            }
            $refTypeName = $refType->getName();
        }

        if (ResultRow::class === $refTypeName) {
            // Yes, user is up-to-date, return its method directly.
            return $callback;
        }

        @\trigger_error(\sprintf("User-defined lazy hydrators callback should use a %s instance as first parameter.", ResultRow::class), E_USER_DEPRECATED);

        if ('array' === $refTypeName) {
            // User defined hydrator expects an array, consider it
            // wants the primary key value.
            return static fn (ResultRow $row) => $callback($row->extractPrimaryKey()->all());
        }
        if (null !== $refTypeName) {
            // We cannot pass an array here, take the first value instead.
            // We are not sure the first primary key value has the actual
            // expected type, but at least we won't give an array for it.
            return static fn (ResultRow $row) => $callback($row->extractPrimaryKey()->first());
        }

        // Extract primary key value in a backward-compatible way.
        // This means that if the primary key value has only value
        // we extract it and give it as-is.
        return static function (ResultRow $row) use ($callback) {
            $primaryKeyValue = $row->extractPrimaryKey();
            if (1 === \count($primaryKeyValue)) {
                return $callback($primaryKeyValue->first());
            }
            return $callback($primaryKeyValue->all());
        };
    }

    /**
     * Create a lazy collection wrapper for method.
     */
    private function createLazyCollection($callback): callable
    {
        if (\is_callable($callback)) {
            $callback = \Closure::fromCallable($callback);
        } else if (\method_exists($this, $callback)) {
            $callback = \Closure::fromCallable([$this, $callback]);
        } else {
            throw new \InvalidArgumentException("Lazy collection initializer must be a callable or a repository method name.");
        }

        $callbackReturnsIterable = false;

        // Check for callback return type.
        // @todo Later, we should not check for this and just return
        //    the normalized initializer.
        $refFunc = new \ReflectionFunction($callback);
        if ($refFunc->hasReturnType()) {
            $refType = $refFunc->getReturnType();
            if ($refType instanceof \ReflectionNamedType) {
                $refTypeName = $refType->getName();

                try {
                    $refClass = new \ReflectionClass($refType->getName());
                    if ($refClass->name === Collection::class ||
                        $refClass->implementsInterface(Collection::class) ||
                        $refClass->name === \Traversable ||
                        $refClass->implementsInterface(\Traversable::class)
                    ) {
                        $callbackReturnsIterable = true;
                    }
                } catch (\ReflectionException $e) {
                    if ('array' === $refTypeName || 'iterable' === $refTypeName) {
                        $callbackReturnsIterable = true;
                    }
                }
            }
        }

        $callback = $this->userDefinedLazyHydratorNormalize($callback);

        if ($callbackReturnsIterable) {
            return $callback;
        }

        return fn (ResultRow $row) => new ArrayCollection(fn () => $callback($row));
    }

    /**
     * Create a lazy property wrapper for method.
     */
    private function createLazyProperty($callback): callable
    {
        if (\is_callable($callback)) {
            $callback = \Closure::fromCallable($callback);
        } else if (\method_exists($this, $callback)) {
            $callback = \Closure::fromCallable([$this, $callback]);
        } else {
            throw new \InvalidArgumentException("Lazy collection initializer must be a callable or a repository method name.");
        }

        $callbackReturnsLazy = false;
        $refFunc = new \ReflectionFunction($callback);
        if ($refFunc->hasReturnType() && ($refType = $refFunc->getReturnType()) && $refType instanceof \ReflectionNamedType) {
            try {
                $refClass = new \ReflectionClass($refType->getName());
                if ($refClass->name === LazyProperty::class || $refClass->implementsInterface(LazyProperty::class)) {
                    $callbackReturnsLazy = true;
                }
            } catch (\ReflectionException $e) {
                // Can not determine return type.
            }
        }

        if ($callbackReturnsLazy) {
            return $this->userDefinedLazyHydratorNormalize($callback);
        }

        $callback = $this->userDefinedLazyHydratorNormalize($callback);

        // @todo Later here, lazy property may be a ghost object instead.
        return fn (ResultRow $row) => new DefaultLazyProperty(fn () => $callback($row));
    }

    /**
     * @param string $targetEntity
     *   Target repository name.
     * @param string|string[]|Key $sourceKey
     *   Source table key.
     *
    protected function referenceAnyToOne(string $targetEntity, $sourceKey): callable
    {
        if (!$sourceKey instanceof Key) {
            $sourceKey = new Key($sourceKey);
        }

        $targetRepository = $this->getRepositoryRegistry()->getRepository($targetEntity);

        return fn (ResultRow $row) => $targetRepository->findFirst($criteria);
    }
     */

    /**
     * Get raw SQL values normalizer.
     *
     * Allows you to normalize SQL values without the need to override the
     * whole hydrator machinery.
     *
     * @return callable
     *   Callback will receive only one argument, which is supposed to be
     *   typed using the ResultRow class.
     *   If the callback does not type the argument or uses the array type
     *   then a backward compatibility layer will provide an array instead,
     *   but this is unsupported and will be removed later.
     */
    public function getHydratorNormalizer(): callable
    {
        return static fn ($values) => $values;
    }

    /**
     * Create raw values hydrator.
     */
    public function getHydrator(): callable
    {
        $hydrator = null;

        // Very ugly code that will build up an hydrator using the internal
        // goat query runner hydrator. Ideally, we should get rid of this
        // dependency to ocramius/generated-hydrator.
        $runner = $this->runner;
        if ($runner instanceof AbstractRunner) {
            $scopeStealer = \Closure::bind(
                function () {
                    return $this->getHydratorRegistry();
                },
                $runner,
                AbstractRunner::class
            );
            $hydratorRegistry = $scopeStealer();
            if ($hydratorRegistry instanceof HydratorRegistry) {
                $hydrator = $hydratorRegistry->getHydrator($this->getClassName());
            }
        }
        if (!$hydrator) {
            throw new \InvalidArgumentException("Cannot hydrate or extract instance date without an hydrator.");
        }

        $nornmalizer = $this->getHydratorNormalizer();
        // Backward compatility layer in the next line.
        $nornmalizer = $this->userDefinedNormalizerNormalize($nornmalizer);

        // Build lazy property hydrators. Both createLazyCollection() and
        // createLazyProperty() methods will normalizer the user given callbacks
        // to a callback using the ResultRow class as argument, for easier key
        // extraction from values.
        $lazyProperties = [];
        if ($collectionMapping = $this->defineLazyCollectionMapping()) {
            foreach ($collectionMapping as $propertyName => $initializer) {
                $lazyProperties[$propertyName] = $this->createLazyCollection($initializer);
            }
        }
        if ($propertyMapping = $this->defineLazyPropertyMapping()) {
            foreach ($propertyMapping as $propertyName => $initializer) {
                $lazyProperties[$propertyName] = $this->createLazyProperty($initializer);
            }
        }

        $definition = $this->getRepositoryDefinition();
        if (!$definition->hasDatabasePrimaryKey()) {
            throw new \InvalidArgumentException("Default hydration process requires that the repository defines a primary key.");
        }
        $primaryKey = $definition->getDatabasePrimaryKey();

        if ($lazyProperties) {
            return function (array $values) use ($lazyProperties, $primaryKey, $hydrator, $nornmalizer) {
                $values = new ResultRow($primaryKey, $values);
                $nornmalizer($values);
                // Call all lazy property hydrators.
                foreach ($lazyProperties as $propertyName => $callback) {
                    $values->set($propertyName, $callback($values));
                }
                return $hydrator($values->toArray());
            };
        }

        return static function (array $values) use ($nornmalizer, $hydrator, $primaryKey) {
            $values = new ResultRow($primaryKey, $values);
            $nornmalizer($values);

            // Hydrator gets an array as of now because it is implemented
            // outside of this API.
            return $hydrator($values->toArray());
        };
    }

    /**
     * {@inheritdoc}
     */
    public function createInstance(array $values)
    {
        // @todo Should this use lazy properties or not?
        return ($this->getHydrator())($values);
    }

    /**
     * Reduce given value set based on keys using the allowed key filter.
     *
     * @deprecated
     *   This method is wrong.
     */
    protected function reduceValues(array $values, array $allowedKeys): array
    {
        return \array_intersect_key($values, \array_flip($allowedKeys));
    }

    /**
     * Reduce given value set based on keys using the current repository defined columns
     */
    protected function reduceValuesToColumns(array $values): array
    {
        $ret = [];
        foreach ($this->getRepositoryDefinition()->getDatabaseColumns() as $column) {
            \assert($column instanceof DatabaseColumn);
            $columnName = $column->getColumnName();
            if (\array_key_exists($columnName, $values)) {
                $ret[$columnName] = $values[$columnName];
            } else {
                // Attempt with property name.
                $propertyName = $column->getPropertyName();
                if (\array_key_exists($propertyName, $values)) {
                    $ret[$columnName] = $values[$propertyName];
                }
            }
        }
        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function exists($criteria): bool
    {
        $select = $this
            ->getRunner()
            ->getQueryBuilder()
            ->select($this->getTable())
            ->columnExpression('1')
        ;

        $select->whereExpression(RepositoryQuery::expandCriteria($criteria));

        return (bool)$select->range(1)->execute()->fetchField();
    }

    /**
     * {@inheritdoc}
     */
    public function findOne($id, $raiseErrorOnMissing = true)
    {
        $result = $this
            ->createSelect()
            ->where($this->expandPrimaryKey($id))
            ->range(1, 0)
            ->execute()
        ;

        if ($result->count()) {
            return $result->fetch();
        }

        if ($raiseErrorOnMissing) {
            throw new EntityNotFoundError();
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(array $idList, bool $raiseErrorOnMissing = false): RepositoryResult
    {
        $select = $this->createSelect();
        $orWhere = $select->getWhere()->or();

        foreach ($idList as $id) {
            $orWhere->condition($this->expandPrimaryKey($id));
        }

        return new GoatQueryRepositoryResult($select->execute());
    }

    /**
     * {@inheritdoc}
     */
    public function findFirst($criteria, bool $raiseErrorOnMissing = false)
    {
        $result = $this->createSelect($criteria)->range(1, 0)->execute();

        if ($result->count()) {
            return $result->fetch();
        }

        if ($raiseErrorOnMissing) {
            throw new EntityNotFoundError();
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function findSome($criteria, int $limit = 100) : RepositoryResult
    {
        return new GoatQueryRepositoryResult(
            $this
                ->createSelect($criteria)
                ->range($limit, 0)
                ->execute()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function query($criteria = null): RepositoryQuery
    {
        return new RepositoryQuery($this->createSelect($criteria));
    }
}

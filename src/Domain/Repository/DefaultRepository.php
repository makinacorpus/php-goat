<?php

declare(strict_types=1);

namespace Goat\Domain\Repository;

use Goat\Hydrator\HydratorInterface;
use Goat\Query\Expression;
use Goat\Query\ExpressionColumn;
use Goat\Query\ExpressionRelation;
use Goat\Query\QueryBuilder;
use Goat\Query\QueryError;
use Goat\Query\SelectQuery;
use Goat\Runner\ResultIterator;
use Goat\Runner\Runner;

/**
 * Table repository is a simple model implementation that works on an arbitrary
 * select query.
 */
class DefaultRepository implements GoatRepositoryInterface
{
    private $class;
    private $columns;
    private $preparedFindOneQuery;
    private $primaryKey = [];
    private $relation;
    private $supportsHydration;
    protected $runner;

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
    public function __construct(Runner $runner, array $primaryKey, $relation)
    {
        $this->class = $this->defineClass();
        $this->primaryKey = $primaryKey;
        $this->runner = $runner;
        if ($relation instanceof ExpressionRelation) {
            $this->relation = $relation;
        } else {
            $this->relation = ExpressionRelation::create($relation);
        }
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

    /**
     * Allow the repository to map method results on hydrated object properties.
     *
     * Using this, you can create lazy-loaded collections and set them on
     * loaded objects, for example, fetching related objects.
     *
     * This method must return an array in which keys are property names and
     * values are either:
     *
     *  - a method name, the method must exist on the instance that declares
     *    the defineLazyCollectionMapping() method,
     *
     *  - an arbitrary callable.
     *
     * If a method name is provided, it will not be lazy loaded, in order to
     * fetch a lazy collection, you will need to return a LazyCollection
     * instance by yourself.
     *
     * If a callable is provided, it will be automatically wrapped into a
     * lazy collection on the object instance.
     *
     * @return string[]|callable[]
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
     * @param mixed $id
     *
     * @return array
     *   Keys are column names, values
     */
    protected final function expandPrimaryKey($id): array
    {
        if (!$this->primaryKey) {
            throw new QueryError("repository has no primary key defined");
        }

        if (!\is_array($id)) {
            $id = [$id];
        }
        if (\count($id) !== \count($this->primaryKey)) {
            throw new QueryError(\sprintf("column count mismatch between primary key and user input, awaiting columns (in that order): '%s'", \implode("', '", $this->primaryKey)));
        }

        $ret = [];

        $relationAlias = $this->getRelation()->getAlias();
        foreach (\array_combine($this->primaryKey, $id) as $column => $value) {
            // Repository can choose to actually already have prefixed the column
            // primary key using the alias, let's cover this use case too: this
            // might happen if either the original select query do need
            // deambiguation from the start, or if the API user was extra
            // precautionous.
            if (false === \strpos($column, '.')) {
                $ret[$relationAlias.'.'.$column] = $value;
            } else {
                $ret[$column] = $value;
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
     * {@inheritdoc}
     */
    public final function getClassName(): string
    {
        return $this->class;
    }

    /**
     * {@inheritdoc}
     */
    public function getRelation(): ExpressionRelation
    {
        return clone $this->relation;
    }

    /**
     * Does this instance supports object hydration
     */
    final protected function supportsHydration(): bool
    {
        return $this->supportsHydration ?? ($this->supportsHydration = \method_exists($this->runner, 'getHydratorMap'));
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
     * Append given columns to select
     */
    private final function appendColumnsToSelect(SelectQuery $select, iterable $columns, string $relationAlias): void
    {
        foreach ($columns as $alias => $column) {
            $columnExpr = $this->normalizeColumn($column, $relationAlias);
            if (\is_int($alias)) {
                $select->column($columnExpr);
            } else {
                $select->column($columnExpr, $alias);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createSelect($criteria = null, bool $withColumns = true): SelectQuery
    {
        $select = $this->getRunner()->getQueryBuilder()->select($this->relation);
        $withHydrator = false;

        if ($withColumns) {
            $relationAlias = ($relation = $this->getRelation())->getAlias() ?? $relation->getName();

            if ($columns = $this->defineSelectColumns()) {
                $this->appendColumnsToSelect($select, $columns, $relationAlias);
            } else if ($columns = $this->getColumns()) {
                $this->appendColumnsToSelect($select, $columns, $relationAlias);
            } else {
                $select->column(new ExpressionColumn('*', $relationAlias));
            }

            if ($withHydrator = $this->supportsHydration()) {
                $select->setOption('hydrator', $this->getHydratorWithLazyProperties());
            }
        }

        if ($criteria) {
            $select->expression(RepositoryQuery::expandCriteria($criteria));
        }
        if (!$withHydrator) {
            $select->setOption('class', $this->class);
        }

        $select->setOption('types', $this->defineSelectColumnsTypes());

        return $select;
    }

    /**
     * Does this repository has columns
     */
    public function hasColumns(): bool
    {
        return !empty($this->getColumns());
    }

    /**
     * Get selected relation columns
     *
     * @return string[]
     *   Column names, keys might be either numerical (no alias) or a string
     *   case in which it'll be used as column alias
     */
    public function getColumns(): array
    {
        return $this->columns ?? ($this->columns = \array_values($this->defineColumns()));
    }

    /**
     * {@inheritdoc}
     */
    public function hasPrimaryKey(): bool
    {
        return isset($this->primaryKey);
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryKeyCount(): int
    {
        return empty($this->primaryKey) ? 0 : \count($this->primaryKey);
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryKey(): array
    {
        if (empty($this->primaryKey)) {
            throw new \LogicException(\sprintf("%s repository for entity %s has no primary key defined", __CLASS__, $this->class));
        }

        return $this->primaryKey;
    }

    /**
     * Create a lazy collection wrapper for method.
     */
    private function createLazyCollection(string $method): callable
    {
        if (!\method_exists($this, $method)) {
            throw new \InvalidArgumentException(\sprintf("Method %s::%s() does not exists", static::class, $method));
        }

        return function (...$arguments) use ($method): LazyCollection {
            return \call_user_func([$this, $method], ...$arguments);
        };
    }

    /**
     * Create a lazy property wrapper for method.
     */
    private function createLazyProperty(string $method): callable
    {
        if (!\method_exists($this, $method)) {
            throw new \InvalidArgumentException(\sprintf("Method %s::%s() does not exists", static::class, $method));
        }

        return function (...$arguments) use ($method): LazyProperty {
            return \call_user_func([$this, $method], ...$arguments);
        };
    }

    /**
     * Create hydrator with lazy properties hydration
     */
    final protected function getHydratorWithLazyProperties(): HydratorInterface
    {
        $hydrator = $this->getHydrator();

        $lazyProperties = [];

        if ($collectionMapping = $this->defineLazyCollectionMapping()) {
            foreach ($collectionMapping as $columnAlias => $value) {
                if (\is_callable($value)) {
                    $lazyProperties[$columnAlias] = $value;
                } else if (\is_string($value)) {
                    $lazyProperties[$columnAlias] = $this->createLazyCollection($value); 
                } else {
                    throw new \InvalidArgumentException();
                }
            }
        }

        if ($propertyMapping = $this->defineLazyPropertyMapping()) {
            foreach ($propertyMapping as $columnAlias => $value) {
                if (\is_callable($value)) {
                    $lazyProperties[$columnAlias] = $value;
                } else if (\is_string($value)) {
                    $lazyProperties[$columnAlias] = $this->createLazyProperty($value);
                } else {
                    throw new \InvalidArgumentException();
                }
            }
        }

        if ($lazyProperties) {
            return new LazyCollectionHydrator($hydrator, $lazyProperties, $this->primaryKey);
        }

        return $hydrator;
    }

    /**
     * Create raw values hydrator
     */
    final public function getHydrator(): HydratorInterface
    {
        if ($this->supportsHydration()) {
            return $this->runner->getHydratorMap()->get($this->class);
        }

        throw new \InvalidArgumentException("Cannot hydrate or extract instance date without an hydrator");
    }

    /**
     * {@inheritdoc}
     */
    public function createInstance(array $values)
    {
        return $this->getHydrator()->createAndHydrateInstance($values);
    }

    /**
     * {@inheritdoc}
     */
    public function createInstanceFrom($entity)
    {
        return $this->createInstance($this->extractValues($entity));
    }

    /**
     * Reduce given value set based on keys using the allowed key filter
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
        return $this->reduceValues($values, $this->getColumns());
    }

    /**
     * {@inheritdoc}
     */
    public function extractValues($entity, bool $withPrimaryKey = false): array
    {
        $hydrator = $this->getHydrator();
        $values = $hydrator->extractValues($entity);

        if (!$withPrimaryKey) {
            foreach ($this->getPrimaryKey() as $column) {
                unset($values[$column]);
            }
        }

        if ($columns = $this->defineColumns()) {
            $values = \array_intersect_key($values, \array_flip($columns));
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function exists($criteria): bool
    {
        $select = $this
            ->getRunner()
            ->getQueryBuilder()
            ->select($this->relation)
            ->columnExpression('1')
        ;

        $select->expression(RepositoryQuery::expandCriteria($criteria));

        return (bool)$select->range(1)->execute()->fetchField();
    }

    /**
     * Get prepared statement identifier
     */
    protected function getPreparedStatementIdPrefix(): string
    {
        return \str_replace('\\', '__', static::class);
    }

    /**
     * {@inheritdoc}
     */
    public function findOne($id, $raiseErrorOnMissing = true)
    {
        if (!$this->preparedFindOneQuery) {
            $this->preparedFindOneQuery = $this->runner->getQueryBuilder()->prepare(
                function (QueryBuilder $builder) use ($id) {
                    $select = $this->createSelect();
                    foreach ($this->expandPrimaryKey($id) as $column => $value) {
                        $select->condition($column, $value);
                    }
                    return $select->range(1, 0);
                },
                $this->getPreparedStatementIdPrefix().'_find_one'
            );
        }

        // If primary key comes in as an array, it means that keys are column
        // names, it will conflict with execute() method which will attempt to
        // pass those as named parameters.
        if (\is_array($id)) {
            $args = \array_values($id);
        } else {
            $args = [$id];
        }

        $result = $this->preparedFindOneQuery->execute($args);

        if ($result->count()) {
            return $result->fetch();
        }

        if ($raiseErrorOnMissing) {
            throw new EntityNotFoundError();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(array $idList, bool $raiseErrorOnMissing = false): ResultIterator
    {
        $select = $this->createSelect();
        $orWhere = $select->getWhere()->or();

        foreach ($idList as $id) {
            $pkWhere = $orWhere->and();
            foreach ($this->expandPrimaryKey($id) as $column => $value) {
                $pkWhere->condition($column, $value);
            }
        }

        return $select->execute();
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
    }

    /**
     * {@inheritdoc}
     */
    public function findSome($criteria, int $limit = 100) : iterable
    {
        return $this->createSelect($criteria)->range($limit, 0)->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function query($criteria = null): RepositoryQuery
    {
        return new RepositoryQuery($this->createSelect($criteria));
    }
}

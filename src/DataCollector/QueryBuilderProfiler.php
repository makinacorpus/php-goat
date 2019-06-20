<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\DataCollector;

use Goat\Query\DeleteQuery;
use Goat\Query\InsertQueryQuery;
use Goat\Query\InsertValuesQuery;
use Goat\Query\Query;
use Goat\Query\QueryBuilder;
use Goat\Query\SelectQuery;
use Goat\Query\UpdateQuery;
use Goat\Query\PreparedQuery;
use Goat\Query\QueryError;

final class QueryBuilderProfiler implements QueryBuilder
{
    private $queryBuilder;
    private $profiler;

    /**
     * Default constructor
     */
    public function __construct(QueryBuilder $queryBuilder, RunnerProfiler $profiler)
    {
        $this->queryBuilder = $queryBuilder;
        $this->profiler = $profiler;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(callable $callback, ?string $identifier = null): Query
    {
        $query = \call_user_func($callback, $this);

        if (!$query instanceof Query) {
            throw new QueryError(\sprintf(
                "Callable passed to %s::%s must return a %s object",
                __CLASS__, __METHOD__, Query::class
            ));
        }

        return new PreparedQuery($this->profiler, $query, $identifier);
    }

    /**
     * {@inheritdoc}
     */
    public function select($relation = null, ?string $alias = null): SelectQuery
    {
        $query = $this->queryBuilder->select($relation, $alias);
        $query->setRunner($this->profiler);

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function update($relation, ?string $alias = null): UpdateQuery
    {
        $query = $this->queryBuilder->update($relation, $alias);
        $query->setRunner($this->profiler);

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function insertValues($relation): InsertValuesQuery
    {
        $query = $this->queryBuilder->insertValues($relation);
        $query->setRunner($this->profiler);

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function insertQuery($relation): InsertQueryQuery
    {
        $query = $this->queryBuilder->insertQuery($relation);
        $query->setRunner($this->profiler);

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($relation, ?string $alias = null): DeleteQuery
    {
        $query = $this->queryBuilder->delete($relation, $alias);
        $query->setRunner($this->profiler);

        return $query;
    }
}

<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\Calista;

use Goat\Domain\Repository\RepositoryInterface;
use Goat\Domain\Repository\RepositoryQuery;
use Goat\Query\Query as GoatQuery;
use MakinaCorpus\Calista\Bridge\Goat\GoatDatasourceResult;
use MakinaCorpus\Calista\Datasource\DatasourceInterface;
use MakinaCorpus\Calista\Datasource\DatasourceResultInterface;
use MakinaCorpus\Calista\Query\Query;

class GoatDatasource implements DatasourceInterface
{
    private $columnMap = [];
    private $repository;

    /**
     * Default constructor
     */
    public function __construct(RepositoryInterface $repository, array $columnMap = [])
    {
        $this->columnMap = $columnMap;
        $this->repository = $repository;
    }

    /**
     * {@inheritdoc}
     */
    public function getItemClass(): string
    {
        return $this->repository->getClassName();
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters(): array
    {
        return []; // @todo
    }

    /**
     * {@inheritdoc}
     */
    public function getSorts(): array
    {
        return []; // @todo
    }

    /**
     * {@inheritdoc}
     */
    public function supportsStreaming(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsPagination(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsFulltextSearch(): bool
    {
        return false; // @todo is this automatically implementable?
    }

    /**
     * {@inheritdoc}
     */
    public function validateItems(Query $query, array $idList): bool
    {
        return false; // @todo easy to implement, do it.
    }

    /**
     * Expand column name using internal column map
     */
    protected function expandColumn(string $field): string
    {
        return $this->columnMap[$field] ?? $field;
    }

    /**
     * Handler order
     */
    protected function handleSort(RepositoryQuery $repoQuery, Query $query): void
    {
        // Order by is supposed to happen with column mapping nevertheless you
        // might want to override this and proceed with more complex SQL statements
        // such as CASE/WHEN statements.
        $sortOrder = $query->getSortOrder() === Query::SORT_DESC ? GoatQuery::ORDER_DESC : GoatQuery::ORDER_ASC;
        $sortColumn = $this->expandColumn($query->getSortField());
        $repoQuery->orderBy($sortColumn, $sortOrder);

        // @todo set a default sort order, always
    }

    /**
     * Handler filters
     */
    protected function handleFilters(RepositoryQuery $repoQuery, Query $query): void
    {
        // @todo deal with filters
        $criteria = [];
        $repoQuery->with($criteria);
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(Query $query): DatasourceResultInterface
    {
        $repoQuery = $this->repository->query();
        $this->handleFilters($repoQuery, $query);
        $this->handleSort($repoQuery, $query);

        $paginator = $repoQuery->paginate();
        $ret = new GoatDatasourceResult($this->getItemClass(), $paginator->getResult());
        $ret->setTotalItemCount($paginator->getTotalCount());

        return $ret;
    }
}

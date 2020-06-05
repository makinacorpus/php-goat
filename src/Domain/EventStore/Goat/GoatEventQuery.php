<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore\Goat;

use Goat\Domain\EventStore\AbstractEventQuery;
use Goat\Domain\EventStore\EventStream;

final class GoatEventQuery extends AbstractEventQuery
{
    private GoatEventStore $store;

    public function __construct(GoatEventStore $store)
    {
        $this->store = $store;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): EventStream
    {
        $select = $this
            ->store
            ->createSelectQuery($this)
            ->columnExpression("event.*")
            ->columns(['index.aggregate_type', 'index.aggregate_root', 'index.namespace'])
        ;

        if ($this->limit) {
            $select->range($this->limit);
        }

        return new GoatEventStream($select->execute(), $this->store);
    }
}

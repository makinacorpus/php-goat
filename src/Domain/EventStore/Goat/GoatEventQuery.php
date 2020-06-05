<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore\Goat;

use Goat\Domain\EventStore\AbstractEventQuery;
use Goat\Domain\EventStore\EventStream;

final class GoatEventQuery extends AbstractEventQuery
{
    private GoatEventStore $eventStore;

    public function __construct(GoatEventStore $eventStore)
    {
        $this->eventStore = $eventStore;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(): EventStream
    {
        $select = $this
            ->eventStore
            ->createSelectQuery($this)
            ->columnExpression("event.*")
            ->columns(['index.aggregate_type', 'index.aggregate_root', 'index.namespace'])
        ;

        if ($this->limit) {
            $select->range($this->limit);
        }

        return new GoatEventStream($select->execute(), $this->eventStore);
    }
}

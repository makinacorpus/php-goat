<?php

declare(strict_types=1);

namespace Goat\Domain\EventStore\Goat;

use Goat\Domain\EventStore\AbstractEventQuery;
use Goat\Domain\EventStore\Event;
use Goat\Domain\EventStore\EventStream;
use Goat\Runner\ResultIterator;

final class GoatEventQuery extends AbstractEventQuery
{
    /**
     * @var GoatEventStore
     */
    private $store;

    /**
     * Default constructor.
     */
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

        $result = $select->execute();

        return new class($result, $this->store) implements \IteratorAggregate, EventStream
        {
            private $result;
            private $store;

            /**
             * Default constructor.
             */
            public function __construct(ResultIterator $result, GoatEventStore $store)
            {
                $this->result = $result;
                $this->store = $store;
            }

            /**
             * {@inheritdoc}
             */
            public function count()
            {
                return $this->result->countRows();
            }

            /**
             * {@inheritdoc}
             */
            public function getIterator()
            {
                foreach ($this->result as $row) {
                    yield $this->store->hydrateEvent($row);
                }
            }

            /**
             * {@inheritdoc}
             */
            public function fetch(): ?Event
            {
                if ($row = $this->result->fetch()) {
                    return $this->store->hydrateEvent($row);
                }
                return null;
            }
        };
    }
}

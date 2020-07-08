<?php

declare(strict_types=1);

namespace Goat\EventStore\Tests;

use Goat\Dispatcher\Message;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class MockMessage2 implements Message
{
    private $foobar;
    private $id;
    private $rootId;
    private $type;

    public function __construct($foobar, $type, ?UuidInterface $id = null, ?UuidInterface $rootId = null)
    {
        $this->foobar = $foobar;
        $this->id = $id;
        $this->rootId = $rootId;
        $this->type = $type;
    }

    public function getFoobar()
    {
        return $this->foobar;
    }

    /**
     * {@inheritdoc}
     */
    public function getAggregateType(): ?string
    {
        return self::class;
    }

    /**
     * {@inheritdoc}
     */
    public function getAggregateId(): UuidInterface
    {
        return $this->id ?? ($this->id = Uuid::uuid4());
    }

    /**
     * {@inheritdoc}
     */
    public function getAggregateRoot(): ?UuidInterface
    {
        return $this->rootId;
    }
}

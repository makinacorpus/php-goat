<?php

declare(strict_types=1);

namespace Goat\Domain;

use Psr\Log\LoggerAwareTrait;

trait DebuggableTrait
{
    use LoggerAwareTrait;

    /** @var bool */
    protected $debug = false;

    /**
     * Toggle debug mode
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }
}

<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;

final class ConnectionDataCollector extends DataCollector implements LateDataCollectorInterface
{
    private $profiler;

    /**
     * Default constructor
     */
    public function __construct(RunnerProfiler $profiler)
    {
        $this->profiler = $profiler;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        $this->data = $this->profiler->getCollectedData();
    }

    /**
     * Get collected data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get executed queries raw SQL
     */
    public function getQueries(): array
    {
        return $this->data['queries'] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function lateCollect()
    {
        return $this->profiler->getCollectedData();
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'goat_runner';
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->data = [];
    }
}

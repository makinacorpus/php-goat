<?php

declare(strict_types=1);

namespace Goat\Dispatcher\RetryStrategy;

/**
 * Object is voluntarily mutable to allow retry strategy decorators to alter
 * default implementation response.
 */
final class RetryStrategyResponse
{
    const DEFAULT_DELAI = 100;
    const DEFAULT_MAX_COUNT = 4;

    /** Human readable reason for instrumentation, will end up in logs. */
    private ?string $reason = null;

    /** Retry delai, in millisecs. */
    private int $delay = self::DEFAULT_DELAI;

    /** Should retry or not. */
    private bool $shouldRetry = false;

    /** Maximum number of retries */
    private int $maxCount = 4;

    /** Please use static constructors. */
    private function __construct(?string $reason = null)
    {
        $this->reason = $reason;
    }

    /**
     * Create a retry response.
     */
    public static function retry(?string $reason = null): self
    {
        $ret = new self($reason);
        $ret->shouldRetry = true;

        return $ret;
    }

    /**
     * Create a reject response.
     */
    public static function reject(?string $reason = null): self
    {
        $ret = new self($reason);
        $ret->shouldRetry = false;

        return $ret;
    }

    public function withDelay(int $millisecs): self
    {
        $this->delay = $millisecs;

        return $this;
    }

    public function withDefaultDelay(): self
    {
        $this->delay = self::DEFAULT_DELAI;

        return $this;
    }

    public function withMaxCount(int $maxCount): self
    {
        $this->maxCount = $maxCount;

        return $this;
    }

    public function withDefaultCount(): self
    {
        $this->maxCount = self::DEFAULT_MAX_COUNT;

        return $this;
    }

    public function forceReject(): self
    {
        $this->shouldRetry = false;

        return $this;
    }

    public function forceRetry(): self
    {
        $this->shouldRetry = true;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getDelay(): int
    {
        return $this->delay;
    }

    public function getMaxCount(): int
    {
        return $this->maxCount;
    }

    public function shouldRetry(): bool
    {
        return $this->shouldRetry;
    }
}

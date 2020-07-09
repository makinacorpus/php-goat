<?php

declare(strict_types=1);

namespace Goat\Dispatcher\HandlerLocator;

use Goat\Dispatcher\Error\HandlerNotFoundError;

final class HandlerReference
{
    public string $className;
    public string $methodName;
    public string $serviceId;

    public function __construct(string $className, string $methodName, string $serviceId)
    {
        $this->className = $className;
        $this->methodName = $methodName;
        $this->serviceId = $serviceId;
    }

    /**
     * Create instance from arbitrary array data. 
     */
    public static function fromArray(array $data): self
    {
        if (\count($data) !== 3 && !isset($data[2])) {
            throw new HandlerNotFoundError("Invalid handler definition");
        }

        return new self($data[0], $data[1], $data[2]);
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [$this->className, $this->methodName, $this->serviceId];
    }
}

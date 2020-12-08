<?php

declare(strict_types=1);

namespace Goat\Dispatcher\HandlerLocator;

final class HandlerReference
{
    public string $className;
    public string $methodName;
    public string $serviceId;

    public function __construct(string $className, string $methodName, ?string $serviceId = null)
    {
        $this->className = $className;
        $this->methodName = $methodName;
        $this->serviceId = $serviceId ?? $className;
    }
}

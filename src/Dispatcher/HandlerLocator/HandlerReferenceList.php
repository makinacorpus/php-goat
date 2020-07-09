<?php

declare(strict_types=1);

namespace Goat\Dispatcher\HandlerLocator;

/**
 * Handler reference finder and runtime.
 */
final class HandlerReferenceList
{
    private ?string $parentClass = null;
    private bool $allowMultiple = false;
    /** @var array<string,CallableReference> */
    private array $references = [];
    /** @var array<string, string[][]> */
    private array $definition = [];

    /**
     * Create new instance for building (introspection).
     */
    public static function create(?string $parentClass = null, bool $allowMultiple = false): self
    {
        $ret = new self();
        $ret->allowMultiple = $allowMultiple;
        $ret->parentClass = $parentClass;

        return $ret;
    }

    /**
     * Create new instance from array (runtime).
     */
    public static function fromArray(array $data, string $parentClass = null, bool $allowMultiple = false): self
    {
        $ret = new self();
        $ret->allowMultiple = $allowMultiple;
        $ret->definition = $data;
        $ret->parentClass = $parentClass;

        return $ret;
    }

    public function toArray(): array
    {
        return \array_map(
            fn (array $references) => \array_map(
                fn (HandlerReference $reference) => $reference->toArray(),
                $references
            ),
            $this->references
        );
    }

    public function appendFromClass(string $handlerClassName, ?string $id = null): void
    {
        foreach (self::findHandlerMethods($handlerClassName, $id ?? $handlerClassName, $this->parentClass) as $reference) {
            \assert($reference instanceof HandlerReference);

            $this->append($reference);
        }
    }

    /**
     * @return null|HandlerReference
     */
    public function first(string $className): ?HandlerReference
    {
        return $this->lookup($className)[0] ?? null;
    }

    /**
     * @return HandlerReference[]
     */
    public function all(string $className): iterable
    {
        return $this->lookup($className) ?? [];
    }

    private function lookup(string $className): ?array
    {
        if ($reference = $this->references[$className] ?? null) {
            return $reference;
        }
        if ($definitions = $this->definition[$className] ?? null) {
            return $this->references[$className] = \array_map(fn ($data) => HandlerReference::fromArray($data), $definitions);
        }
        return null;
    }

    private function append(HandlerReference $reference): void
    {
        $existing = $this->references[$reference->className][0] ?? null;

        if ($existing && !$this->allowMultiple) {
            \assert($existing instanceof HandlerReference);

            throw new \LogicException(\sprintf(
                "Handler for command class %s is already defined using %s::%s, found %s::%s",
                $reference->className,
                $existing->serviceId,
                $existing->methodName,
                $reference->serviceId,
                $reference->methodName
            ));
        }

        $this->references[$reference->className][] = $reference;
    }

    /**
     * Here lies magic, beware.
     */
    public static function findHandlerMethods(string $handlerClassName, string $id, ?string $parentClass = null): iterable
    {
        $class = new \ReflectionClass($handlerClassName);

        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            \assert($method instanceof \ReflectionMethod);

            if ($method->isStatic()) {
                continue;
            }

            $parameters = $method->getParameters();
            if (1 !== \count($parameters)) {
                continue;
            }

            $parameter = \reset($parameters);
            \assert($parameter instanceof \ReflectionParameter);

            if (!$parameter->hasType()) {
                continue;
            }

            $type = $parameter->getType();
            \assert($type instanceof \ReflectionType);

            if ($type->isBuiltin()) {
                continue;
            }

            $parameterClassName = $type->getName();

            if (!$parentClass || (
                \class_exists($parentClass) &&
                \in_array($parentClass, \class_implements($parameterClassName))
            )) {
                yield new HandlerReference($parameterClassName, $method->getName(), $id);
            }
        }
    }
}

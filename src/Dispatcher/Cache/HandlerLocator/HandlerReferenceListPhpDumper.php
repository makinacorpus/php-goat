<?php

declare(strict_types=1);

namespace Goat\Dispatcher\Cache\HandlerLocator;

use Goat\Dispatcher\HandlerLocator\DefaultHandlerReferenceList;
use Goat\Dispatcher\HandlerLocator\HandlerReference;
use Symfony\Component\Filesystem\Exception\IOException;

class HandlerReferenceListPhpDumper
{
    private string $filename;
    private bool $allowMultiple;
    private array $references = [];

    public function __construct(string $filename, bool $allowMultiple = false)
    {
        $this->filename = $filename;
        $this->allowMultiple = $allowMultiple;
    }

    public static function getFilename(string $kernelCacheDirectory, string $tag = 'command'): string
    {
        return $kernelCacheDirectory . '/goat_handler_callback_' . $tag . '.php';
    }

    public static function getDumpedClassNamespace(): string
    {
        return 'MakinaCorpus\Goat\Dispatcher\Cache\HandlerLocator\Generated';
    }

    public static function getDumpedClassName(string $tag): string
    {
        return \ucfirst($tag) . 'DumpedHandlerReferenceList';
    }

    public function appendFromClass(
        string $handlerClassName,
        ?string $handlerServiceId = null,
        ?string $parameterInterfaceName = null
    ): void {
        foreach (DefaultHandlerReferenceList::findHandlerMethods(
            $handlerClassName,
            $handlerServiceId,
            $parameterInterfaceName
        ) as $reference) {
            $this->append($reference);
        }
    }

    public function isEmpty(): bool
    {
        return empty($this->references);
    }

    public function delete(): void
    {
        if (\file_exists($this->filename)) {
            if (!@\unlink($this->filename)) {
                throw new IOException(\sprintf("Could not delete file: %s", $this->filename));
            }
        }
    }

    public function dump(string $dumpedClassName): void
    {
        $this->delete();

        if (!$handle = @\fopen($this->filename, 'cw+')) {
            throw new IOException(\sprintf("Could not open file for writing: %s", $this->filename));
        }

        \fwrite($handle, <<<PHP
<?php

declare(strict_types=1);

namespace MakinaCorpus\Goat\Dispatcher\Cache\HandlerLocator\Generated;

use Goat\Dispatcher\HandlerLocator\HandlerReference;
use Goat\Dispatcher\HandlerLocator\HandlerReferenceList;

final class {$dumpedClassName} implements HandlerReferenceList
{
    public function first(string \$className): ?HandlerReference
    {
        return \$this->doFind(\$className)[0] ?? null;
    }

    public function all(string \$className): iterable
    {
        return \$this->doFind(\$className) ?? [];
    }

    private function doFind(string \$className): ?array
    {
        switch (\$className) {
PHP
        );
        \fwrite($handle, "\n");

        foreach ($this->references as $className => $references) {
            $escapedClassName = \addslashes($className);

            \fwrite($handle, <<<PHP
            case '{$escapedClassName}':
                return [
PHP
            );
            \fwrite($handle, "\n");

            foreach ($references as $reference) {
                \assert($reference instanceof HandlerReference);

                $escapedHandlerClassName = \addslashes($reference->className);
                $escapedMethodName = \addslashes($reference->methodName);
                $escapedServiceId = \addslashes($reference->serviceId);

                \fwrite($handle, <<<PHP
                    new HandlerReference(
                        '{$escapedHandlerClassName}',
                        '{$escapedMethodName}',
                        '{$escapedServiceId}'
                    ),
PHP
                );
                \fwrite($handle, "\n");
            }

            \fwrite($handle, <<<PHP
                ];
PHP
            );
            \fwrite($handle, "\n");
        }

        \fwrite($handle, <<<PHP
            default:
                return null;
        }
    }
}
PHP
        );
        \fwrite($handle, "\n");
        \fclose($handle);
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
}

<?php

declare(strict_types=1);

namespace Goat\Normalization;

/**
 * This class will convert things such as:
 *
 *  - Foo\Shop\Domain\Order\Command\BasketProductAdd
 *  - Foo\Shop\Domain\Order\Event\BasketProductAdded
 *  - Foo\Shop\Domain\Catalogue\Model\Product
 *
 * To respectively those:
 *
 *  - FooShop.Order.Command.BasketProductAdd
 *  - FooShop.Order.Event.BasketProductAdded
 *  - FooShop.Catalogue.Model.Product
 *
 * Considering that the namespace "Foo\Shop\Domain" is the prefix, and must be
 * replaced with the canonical application name "FooShop".
 *
 * Considering that is has the form:
 *
 *  - Prefix\ClassName
 *
 * Where:
 *
 *  - "Prefix" is a namespace prefix, any one, that will aways be replaced
 *    using the given "AppName" string.
 *  - "ClassName" is the rest of the class FQDN.
 *
 * Final result is formatted the following way:
 *
 *  - AppName.ClassName where all remaining "\" will be converted to ".".
 */
class PrefixNameMappingStrategy implements NameMappingStrategy
{
    private string $appName;
    private string $prefix;
    private int $prefixLength;

    public function __construct(string $appName, string $classPrefix)
    {
        $this->appName = $appName;
        $this->prefix = \trim($classPrefix, '\\') . '\\';
        $this->prefixLength = \strlen($this->prefix);
    }

    /**
     * {@inheritdoc}
     */
    public function logicalNameToPhpType(string $logicalName): string
    {
        $pieces = \explode('.', $logicalName);
        if (\count($pieces) < 2 || $this->appName !== $pieces[0]) {
            return $logicalName; // Name does not belong to us.
        }

        return $this->prefix . \implode('\\', \array_slice($pieces, 1));
    }

    /**
     * {@inheritdoc}
     */
    public function phpTypeToLogicalName(string $phpType): string
    {
        $phpType = \trim($phpType, '\\');

        if (!\str_starts_with($phpType, $this->prefix)) {
            return $phpType;
        }

        return $this->appName . '.' . \str_replace('\\', '.', \substr($phpType, $this->prefixLength));
    }
}

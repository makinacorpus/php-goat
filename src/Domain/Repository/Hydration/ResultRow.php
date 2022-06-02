<?php

declare(strict_types=1);

namespace Goat\Domain\Repository\Hydration;

use Goat\Domain\Repository\Key;
use Goat\Domain\Repository\KeyValue;

/**
 * Row being hydrated.
 */
class ResultRow
{
    private Key $primaryKey;
    private ?keyValue $primaryKeyValue = null;
    private array $values;

    public function __construct(Key $primaryKey, array $values)
    {
        $this->primaryKey = $primaryKey;
        $this->values = $values;
    }

    /**
     * Apply a callback globally on the internal object.
     */
    public function apply(callable $callback): void
    {
        $this->values = $callback($this->values);
    }

    public function set(string $key, /* mixed */ $value): void
    {
        $this->values[$key] = $value;
    }

    public function extractPrimaryKey(): KeyValue
    {
        return $this->primaryKeyValue ?? ($this->primaryKeyValue = $this->extractKey($this->primaryKey));
    }

    /** @param string|string[]|Key $key */
    public function extractKey($key): KeyValue
    {
        if (!$key instanceof Key) {
            $key = new Key($key);
        }
        // @todo Handle columnName to propertyName converstion here? This is
        //   supposed to be FAST, very FAST, bad idea to load definition here.
        return $key->extractFrom($this->values);
    }

    public function toArray(): array
    {
        return $this->values;
    }
}

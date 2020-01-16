<?php

namespace MakinaCorpus\Calista\Bridge\Goat;

use Goat\Runner\ResultIterator;
use MakinaCorpus\Calista\Datasource\DatasourceResultInterface;
use MakinaCorpus\Calista\Datasource\DatasourceResultTrait;
use MakinaCorpus\Calista\Datasource\PropertyDescription;

/**
 * Basics for the datasource result interface implementation
 */
class GoatDatasourceResult implements \IteratorAggregate, DatasourceResultInterface
{
    use DatasourceResultTrait;

    private $result;

    /**
     * Default constructor
     */
    public function __construct(string $itemClass, ResultIterator $result, array $properties = [])
    {
        $this->itemClass = $itemClass;
        $this->result = $result;

        foreach ($properties as $index => $property) {
            if (!$property instanceof PropertyDescription) {
                throw new \InvalidArgumentException(\sprintf("property at index %s is not a %s instance", $index, PropertyDescription::class));
            }
        }

        $this->properties = $properties;
    }

    /**
     * {@inheritdoc}
     */
    public function canStream(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return $this->result;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return $this->result->countRows();
    }
}

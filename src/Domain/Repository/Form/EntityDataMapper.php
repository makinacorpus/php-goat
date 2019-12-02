<?php

declare(strict_types=1);

namespace Goat\Domain\Repository\Form;

use Goat\Hydrator\HydratorMap;
use Symfony\Component\Form\DataMapperInterface;

class EntityDataMapper implements DataMapperInterface
{
    private $className;
    private $hydrator;

    public function __construct(HydratorMap $hydratorMap, string $className)
    {
        $this->className = $className;
        $this->hydrator = $hydratorMap->get($this->className);
    }

    public function mapDataToForms($data, $forms)
    {
        $values = $this->hydrator->extractValues($data);

        /** @var \Symfony\Component\Form\Test\FormInterface $form */
        foreach ($forms as $name => $form) {
            if (\array_key_exists($name, $values)) {
                $form->setData($values[$name]);
            }
        }
    }

    public function mapFormsToData($forms, &$data)
    {
        $values = [];

        /** @var \Symfony\Component\Form\Test\FormInterface $form */
        foreach ($forms as $name => $form) {
            $values[$name] = $form->getData();
        }

        $data = $this->hydrator->createAndHydrateInstance($values);
    }
}

<?php

declare(strict_types=1);

namespace Goat\Dispatcher\HandlerLocator;

use Goat\Dispatcher\HandlerLocator;
use Goat\Dispatcher\Error\HandlerNotFoundError;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

final class DefaultHandlerLocator implements HandlerLocator, ContainerAwareInterface
{
    use ContainerAwareTrait;

    private HandlerReferenceList $referenceList;

    /**
     * @param array<string,string>|HandlerReferenceList $references
     */
    public function __construct($references)
    {
        if ($references instanceof HandlerReferenceList) {
            $this->referenceList = $references;
        } else if (\is_array($references)) {
            $this->referenceList = new DefaultHandlerReferenceList(null, false);
            foreach ($references as $id => $className) {
                $this->referenceList->appendFromClass($className, $id);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function find($message): callable
    {
        if (!\is_object($message)) {
            throw new HandlerNotFoundError(\sprintf("Expected object type, '%s' given", \get_type($message)));
        }

        $reference = $this->referenceList->first(\get_class($message));

        if (!$reference) {
            throw new HandlerNotFoundError(\sprintf("Unable to find handler for class %s", \get_class($message)));
        }

        $service = $this->container->get($reference->serviceId);

        return static function (object $message) use ($service, $reference) {
            return $service->{$reference->methodName}($message);
        };
    }
}

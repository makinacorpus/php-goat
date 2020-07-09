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

    public function __construct(?HandlerReferenceList $referenceList = null)
    {
        $this->referenceList = $referenceList ?? HandlerReferenceList::create();
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

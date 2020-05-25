<?php

declare(strict_types=1);

namespace Goat\Domain\Projector;

/**
 * Projector than will NOT be called during event dispatch, you may use those
 * to run one time migration.
 */
interface NoRuntimeProjector extends Projector
{
}

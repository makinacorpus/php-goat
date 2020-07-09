<?php

declare(strict_types=1);

namespace Goat\Preferences\Domain\Message;

/**
 * Delete configuration value
 *
 * [group=settings]
 *
 * @codeCoverageIgnore
 */
final class PreferenceValueDelete
{
    use PreferenceValueMessageTrait;
}

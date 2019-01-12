<?php

declare(strict_types=1);

namespace Goat\Bridge\Symfony\Twig;

use Twig\Extension\AbstractExtension;

/**
 * @codeCoverageIgnore
 */
final class ProfilerExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('goat_format_sql', [$this, 'renderFormattedSql'], ['safe' => ['html']]),
        ];
    }

    /**
     * Format SQL properly
     */
    public function renderFormattedSql(string $raw): string
    {
        if (\class_exists(\SqlFormatter::class)) {
            return \SqlFormatter::format($raw, true);
        }

        return \str_replace("\n", "<br/>", \str_replace("\n\n", "\n", \trim($raw)));
    }
}

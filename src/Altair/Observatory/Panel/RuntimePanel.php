<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory\Panel;

use Altair\Observatory\Contracts\PanelInterface;
use Override;

/**
 * A self-contained reference panel reporting the PHP runtime: version, memory
 * use, OPcache availability and loaded extensions.
 *
 * It depends on nothing outside PHP itself, so it doubles as the worked example
 * for implementing {@see PanelInterface}.
 */
final class RuntimePanel implements PanelInterface
{
    private const int BYTES_PER_MIB = 1048576;

    #[Override]
    public function id(): string
    {
        return 'runtime';
    }

    #[Override]
    public function label(): string
    {
        return 'Runtime';
    }

    #[Override]
    public function icon(): string
    {
        return 'server';
    }

    #[Override]
    public function snapshot(): PanelSnapshot
    {
        $extensions = get_loaded_extensions();
        sort($extensions, SORT_STRING);

        $items = [];
        foreach ($extensions as $extension) {
            $items[] = ['extension' => $extension];
        }

        return new PanelSnapshot(
            PanelStatus::Ok,
            \sprintf('PHP %s', PHP_VERSION),
            [
                'php_version' => PHP_VERSION,
                'memory_mb' => round(memory_get_usage(true) / self::BYTES_PER_MIB, 1),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / self::BYTES_PER_MIB, 1),
                'opcache' => \function_exists('opcache_get_status') ? 'available' : 'unavailable',
                'extensions' => \count($extensions),
            ],
            $items,
        );
    }
}

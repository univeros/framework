<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Support;

/**
 * Deterministic, agent-friendly JSON for `--format=json` output.
 */
final class Json
{
    /**
     * @param array<string, mixed> $data
     */
    public static function encode(array $data): string
    {
        $encoded = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );

        return ($encoded === false ? '{}' : $encoded) . "\n";
    }
}

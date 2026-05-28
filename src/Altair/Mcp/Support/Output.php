<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Support;

/**
 * Keeps subprocess output payloads bounded so a noisy test or analysis run
 * doesn't flood the agent's context. Returns the tail (where failures live).
 */
final class Output
{
    public const int DEFAULT_LIMIT = 6000;

    public static function tail(string $output, int $limit = self::DEFAULT_LIMIT): string
    {
        $trimmed = rtrim($output);
        if (\strlen($trimmed) <= $limit) {
            return $trimmed;
        }

        return "...(truncated)\n" . substr($trimmed, -$limit);
    }
}

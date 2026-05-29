<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Support;

/**
 * Deterministic, human-and-agent-friendly JSON for CLI `--format=json` output.
 * Slashes stay unescaped so file paths read naturally; backslashes in
 * fully-qualified names remain JSON-escaped as required.
 */
final class Json
{
    /**
     * @param array<string, mixed> $data
     */
    public static function encode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}

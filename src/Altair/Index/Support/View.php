<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Support;

use Altair\Index\Model\Symbol;
use Altair\Index\Model\Usage;

/**
 * Human-readable rendering for the index CLI commands. JSON output is built
 * per-command so each tool's structured shape stays explicit (it is the MCP
 * contract); only the terminal-facing rendering is shared here.
 */
final class View
{
    /**
     * @param list<Usage> $usages
     */
    public static function usageLines(array $usages, string $empty = 'No usages found.'): string
    {
        if ($usages === []) {
            return $empty . "\n";
        }

        $lines = [];
        foreach ($usages as $usage) {
            $lines[] = \sprintf(
                '  %-14s %s:%d%s',
                $usage->kind->value,
                $usage->file,
                $usage->line,
                $usage->context !== null ? '  (' . $usage->context . ')' : '',
            );
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<string> $names
     */
    public static function nameLines(array $names, string $empty = 'None found.'): string
    {
        if ($names === []) {
            return $empty . "\n";
        }

        return implode("\n", array_map(static fn(string $n): string => '  ' . $n, $names)) . "\n";
    }

    /**
     * @param list<Symbol> $symbols
     */
    public static function symbolLines(array $symbols, string $empty = 'None found.'): string
    {
        if ($symbols === []) {
            return $empty . "\n";
        }

        $lines = [];
        foreach ($symbols as $symbol) {
            $lines[] = \sprintf('  %-9s %s  (%s:%d)', $symbol->kind->value, $symbol->fqn, $symbol->file, $symbol->line);
        }

        return implode("\n", $lines) . "\n";
    }
}

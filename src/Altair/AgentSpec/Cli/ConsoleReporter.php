<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Cli;

/**
 * Tiny output helper kept separate from the command class so the command
 * stays focused on argument shape and pipeline wiring.
 */
class ConsoleReporter
{
    /**
     * @param list<string> $touched
     */
    public function reportWrite(array $touched): int
    {
        foreach ($touched as $path) {
            echo "wrote ", $path, "\n";
        }

        echo "Generated ", \count($touched), " manifest file(s).\n";

        return 0;
    }

    /**
     * @param list<string> $drifted
     */
    public function reportCheck(array $drifted): int
    {
        if ($drifted === []) {
            echo "All manifests are up to date.\n";

            return 0;
        }

        echo "Manifests are out of date:\n";
        foreach ($drifted as $path) {
            echo "  - ", $path, "\n";
        }
        echo "Run `bin/altair manifest:generate` to regenerate.\n";

        return 1;
    }
}

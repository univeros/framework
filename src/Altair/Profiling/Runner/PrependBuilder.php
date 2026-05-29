<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Runner;

/**
 * Generates a tiny PHP "prepend" script the subprocess auto-loads via
 * `php -d auto_prepend_file=<path>`. The prepend wires excimer at boot and
 * registers a shutdown function to serialise the captured samples to a JSON
 * file the parent reads.
 *
 * Serialised as `[{trace: ..., count: N}, ...]` so the parent can reconstruct
 * a {@see \Altair\Profiling\Model\SampleLog} without depending on excimer
 * itself (the parent might run on a host without the extension loaded).
 */
final readonly class PrependBuilder
{
    public function build(string $outputFile, int $periodUs): string
    {
        $output = var_export($outputFile, true);
        $periodSeconds = $periodUs / 1_000_000;

        return <<<PHP
            <?php
            if (!extension_loaded('excimer')) {
                fwrite(STDERR, "ext-excimer is not loaded — install it to capture profiles.\\n");
                return;
            }

            \$__altairProfFile = {$output};
            \$__altairProf = new \ExcimerProfiler();
            \$__altairProf->setPeriod({$periodSeconds});
            \$__altairProf->start();

            register_shutdown_function(static function () use (\$__altairProf, \$__altairProfFile): void {
                \$__altairProf->stop();
                \$samples = [];
                foreach (\$__altairProf->getLog() as \$entry) {
                    \$samples[] = ['trace' => \$entry->getTrace(), 'count' => \$entry->getEventCount()];
                }
                file_put_contents(\$__altairProfFile, json_encode(\$samples, JSON_UNESCAPED_SLASHES));
            });
            PHP;
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Cli;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Profiling\Exception\SamplerUnavailableException;
use Altair\Profiling\Output\HumanRenderer;
use Altair\Profiling\Runner\SubprocessProfiler;
use Altair\Profiling\Support\Json;
use Altair\Profiling\Support\Workspace;

/**
 * `bin/altair profile:run path/to/script.php` — profile a PHP script in a
 * subprocess with the sampling backend attached, then persist the resulting
 * profile under `.altair/profiles/` for `profile:show` / `profile:compare`.
 *
 * The script can be a one-off bench harness, a CLI command bootstrap, or any
 * PHP entry-point — the profiler does not care, it just spawns `php` with an
 * `auto_prepend_file` that wires excimer. Exit `2` when no sampling backend
 * is loaded (clear install hint shown).
 */
#[Command(
    name: 'profile:run',
    description: 'Profile a PHP script in a subprocess and save the report under .altair/profiles/.',
)]
final readonly class RunCommand
{
    use Workspace;

    public function __invoke(
        #[Argument(description: 'Path to a PHP script to execute under the profiler.')]
        string $script,
        #[Option(description: 'Human-readable label for the saved profile.')]
        ?string $description = null,
        #[Option(description: 'Sampling period in microseconds (default 1000 = 1 ms).', name: 'period-us')]
        int $periodUs = SubprocessProfiler::DEFAULT_PERIOD_US,
        #[Option(description: 'Wall-clock timeout in milliseconds (default 60000).', name: 'timeout-ms')]
        int $timeoutMs = 60_000,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        if (!is_file($script)) {
            echo \sprintf("Script not found: %s\n", $script);

            return 2;
        }

        $profiler = new SubprocessProfiler((string) getcwd());
        $label = $description ?? $script;

        try {
            $report = $profiler->run([$script], $label, $periodUs, $timeoutMs);
        } catch (SamplerUnavailableException $samplerUnavailableException) {
            echo $samplerUnavailableException->getMessage(), "\n";

            return 2;
        }

        $this->storage()->save($report);

        echo $format === 'json'
            ? Json::encode($report->toArray())
            : (new HumanRenderer())->report($report);

        return 0;
    }
}

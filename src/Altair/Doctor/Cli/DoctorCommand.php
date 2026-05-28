<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Doctor\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Doctor\Doctor;
use Altair\Doctor\Exception\DoctorException;
use Altair\Doctor\Output\RendererRegistry;

/**
 * `bin/altair doctor` — run the health checks and emit an agent-actionable
 * report. Exit code is the worst observed status: 0 ok, 1 warn, 2 error.
 */
#[Command(
    name: 'doctor',
    description: 'Run project health checks with agent-actionable output.',
)]
final readonly class DoctorCommand
{
    public function __construct(
        private Doctor $doctor,
        private RendererRegistry $renderers,
    ) {}

    public function __invoke(
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
        #[Option(description: 'Comma-separated check names to run exclusively.')]
        ?string $only = null,
        #[Option(description: 'Comma-separated check names to skip.')]
        ?string $skip = null,
        #[Option(description: 'Attempt safe auto-fixes for checks that support one.')]
        bool $fix = false,
    ): int {
        $report = $this->doctor->run($this->csv($only), $this->csv($skip), $fix);

        try {
            echo $this->renderers->get($format)->render($report);
        } catch (DoctorException $doctorException) {
            echo $doctorException->getMessage(), "\n";

            return 2;
        }

        return $report->exitCode();
    }

    /**
     * @return list<string>
     */
    private function csv(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(trim(...), explode(',', $value)),
            static fn(string $item): bool => $item !== '',
        ));
    }
}

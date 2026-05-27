<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Doctor\Check;

use Altair\Doctor\Contracts\CheckInterface;
use Altair\Doctor\Contracts\ProcessRunnerInterface;
use Altair\Doctor\Result\CheckResult;
use Override;

/**
 * The determinism gate as a check (#74): re-run the configured generators,
 * then `git diff --exit-code` the emitted paths. A non-empty diff means a
 * generator is non-deterministic — agents would see phantom drift.
 *
 * Host apps configure the generators + paths; with none configured the
 * check reports `skipped` rather than a false pass.
 */
final readonly class DeterminismCheck implements CheckInterface
{
    /**
     * @param list<list<string>> $generators argv commands that regenerate emitted content
     * @param list<string>       $paths      paths to diff after regeneration
     */
    public function __construct(
        private ProcessRunnerInterface $runner,
        private string $projectRoot,
        private array $generators = [],
        private array $paths = [],
    ) {}

    #[Override]
    public function name(): string
    {
        return 'determinism_check';
    }

    #[Override]
    public function dependsOn(): array
    {
        return [];
    }

    #[Override]
    public function run(): CheckResult
    {
        if ($this->generators === [] || $this->paths === []) {
            return CheckResult::skipped(
                $this->name(),
                'No generators configured for the determinism gate.',
            );
        }

        foreach ($this->generators as $generator) {
            if (!$this->runner->run($generator, $this->projectRoot)->ok()) {
                return CheckResult::error(
                    $this->name(),
                    'A generator failed to run: ' . implode(' ', $generator),
                );
            }
        }

        $diff = $this->runner->run(['git', 'diff', '--exit-code', ...$this->paths], $this->projectRoot);
        if ($diff->ok()) {
            return CheckResult::ok($this->name(), 'Generated content is byte-stable across regeneration.');
        }

        return CheckResult::error(
            $this->name(),
            'Generated content differs after regeneration — non-determinism detected.',
            'Inspect the diff; sort any unstable iteration order and remove inlined timestamps/machine identifiers.',
        );
    }
}

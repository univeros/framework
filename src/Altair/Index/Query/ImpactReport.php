<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Query;

/**
 * The aggregate blast radius of changing a set of symbols: how many files,
 * tests, and specs reference them, plus the concrete test and spec paths so an
 * agent can run only what is affected before declaring success.
 */
final readonly class ImpactReport
{
    /**
     * @param list<string>                              $symbols
     * @param list<array{file: string, usages: int}>    $byFile
     * @param list<string>                              $testsToRun
     * @param list<string>                              $specsAffected
     */
    public function __construct(
        public array $symbols,
        public int $files,
        public int $tests,
        public int $specs,
        public array $byFile,
        public array $testsToRun,
        public array $specsAffected,
    ) {}

    /**
     * @return array{symbols: list<string>, impact: array{files: int, tests: int, specs: int}, by_file: list<array{file: string, usages: int}>, tests_to_run: list<string>, specs_affected: list<string>}
     */
    public function toArray(): array
    {
        return [
            'symbols' => $this->symbols,
            'impact' => [
                'files' => $this->files,
                'tests' => $this->tests,
                'specs' => $this->specs,
            ],
            'by_file' => $this->byFile,
            'tests_to_run' => $this->testsToRun,
            'specs_affected' => $this->specsAffected,
        ];
    }
}

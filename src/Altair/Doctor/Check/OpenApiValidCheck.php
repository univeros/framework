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
 * Verifies the spec sources emit a well-formed OpenAPI document by running
 * `bin/altair spec:emit-openapi` against a discarded output path. A failure
 * here means the YAML specs are structurally broken — the emitter would
 * refuse them.
 *
 * Distinct from {@see SpecDriftCheck}: drift compares emitted code against
 * spec, this verifies the spec itself can produce a document.
 */
final readonly class OpenApiValidCheck implements CheckInterface
{
    /**
     * @param list<string> $altairBin
     */
    public function __construct(
        private ProcessRunnerInterface $runner,
        private string $projectRoot,
        private string $outputPath = '/dev/null',
        private array $altairBin = ['php', 'bin/altair'],
    ) {}

    #[Override]
    public function name(): string
    {
        return 'openapi_valid';
    }

    #[Override]
    public function dependsOn(): array
    {
        return [];
    }

    #[Override]
    public function run(): CheckResult
    {
        $command = [...$this->altairBin, 'spec:emit-openapi', '--out=' . $this->outputPath];
        if ($this->runner->run($command, $this->projectRoot)->ok()) {
            return CheckResult::ok($this->name(), 'OpenAPI document emits without errors.');
        }

        return CheckResult::error(
            $this->name(),
            'spec:emit-openapi failed — the spec source has structural issues.',
            'Review the YAML specs under api/ and fix the validation errors reported by `bin/altair spec:emit-openapi`.',
        );
    }
}

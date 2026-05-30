<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Examples\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Examples\Configuration\ExamplesSettings;
use Altair\Examples\Library\Contracts\ExampleRepositoryInterface;
use Altair\Examples\Library\Example;

/**
 * `bin/altair examples:test` — run every linked `tested_by` test file via
 * the project's PHPUnit binary. The library is honest only if these pass.
 */
#[Command(
    name: 'examples:test',
    description: 'Run every example\'s linked `tested_by` test file via PHPUnit.',
)]
final readonly class TestCommand
{
    public function __construct(
        private ExampleRepositoryInterface $repository,
        private ExamplesSettings $settings,
    ) {}

    public function __invoke(
        #[Option(description: 'Path to the PHPUnit binary (defaults to vendor/bin/phpunit).')]
        ?string $phpunit = null,
        #[Option(description: 'Skip examples whose tested_by file is missing instead of failing the run.')]
        bool $skipMissing = false,
    ): int {
        $binary = $phpunit ?? $this->settings->projectRoot . '/vendor/bin/phpunit';
        if (!is_file($binary)) {
            echo "PHPUnit binary not found at '{$binary}'. Pass --phpunit=<path>.\n";

            return 2;
        }

        $examples = $this->repository->findAll();
        if ($examples === []) {
            echo "No examples in the library.\n";

            return 0;
        }

        $missing = [];
        $files = [];
        foreach ($examples as $example) {
            $absolute = $this->resolveTestPath($example);
            if (!is_file($absolute)) {
                $missing[] = $example;

                continue;
            }
            $files[] = $absolute;
        }

        if ($missing !== [] && !$skipMissing) {
            echo "The following examples reference missing test files:\n";
            foreach ($missing as $example) {
                echo "  - {$example->id} -> {$example->testedBy}\n";
            }
            echo "Pass --skip-missing to run the remaining tests anyway.\n";

            return 1;
        }

        if ($files === []) {
            echo "No example tests resolved to runnable files.\n";

            return $missing === [] ? 0 : 1;
        }

        $command = $this->buildCommand($binary, $files);
        echo "Running: {$command}\n";

        passthru($command, $status);

        return $status;
    }

    private function resolveTestPath(Example $example): string
    {
        return $this->settings->projectRoot . DIRECTORY_SEPARATOR . ltrim($example->testedBy, '/');
    }

    /**
     * @param list<string> $files
     */
    private function buildCommand(string $binary, array $files): string
    {
        $args = array_map(static fn(string $f): string => escapeshellarg($f), $files);

        return escapeshellarg($binary) . ' ' . implode(' ', $args);
    }
}

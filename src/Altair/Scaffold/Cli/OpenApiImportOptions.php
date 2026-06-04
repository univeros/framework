<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Cli;

/**
 * Resolved set of options for one `openapi:import` invocation.
 *
 * Kept as a small value object so {@see OpenApiImportRunner} can be tested
 * directly without standing up Symfony Console infrastructure.
 */
final readonly class OpenApiImportOptions
{
    public function __construct(
        public string $documentPath,
        public string $projectRoot,
        public ?string $outDir = null,
        public bool $scaffold = false,
        public bool $dryRun = false,
        public bool $force = false,
        public bool $skipUnmappable = false,
        public ?string $persistence = null,
        public ?string $queue = null,
    ) {}
}

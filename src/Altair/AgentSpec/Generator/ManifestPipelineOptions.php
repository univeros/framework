<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Generator;

use Altair\AgentSpec\Model\ModuleManifest;

final readonly class ManifestPipelineOptions
{
    /**
     * @param list<ModuleManifest> $modules Sections contributed by host modules.
     */
    public function __construct(
        public string $monorepoRoot,
        public string $sourceRoot,
        public ?string $testsRoot,
        public string $outputRoot,
        public bool $checkOnly,
        public array $modules = [],
    ) {}
}

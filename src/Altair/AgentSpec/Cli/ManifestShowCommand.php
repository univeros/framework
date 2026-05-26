<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Cli;

use Altair\AgentSpec\Contracts\ManifestRendererInterface;
use Altair\AgentSpec\Contracts\PackageScannerInterface;
use Altair\AgentSpec\Exception\AgentSpecException;
use Altair\AgentSpec\Generator\PackageManifestGenerator;
use Altair\AgentSpec\Reflection\PackageScanner;
use Altair\AgentSpec\Renderer\MarkdownPackageRenderer;
use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;

/**
 * Regenerates and prints exactly one package's manifest to stdout.
 * Resolves the package by its slug (the segment after `univeros/`),
 * so `manifest:show http` matches the `univeros/http` package.
 */
#[Command(
    name: 'manifest:show',
    description: 'Print one package manifest to stdout.',
)]
final class ManifestShowCommand
{
    public function __construct(
        private readonly PackageScannerInterface $scanner = new PackageScanner(),
        private readonly PackageManifestGenerator $generator = new PackageManifestGenerator(),
        private readonly ManifestRendererInterface $renderer = new MarkdownPackageRenderer(),
        private readonly PathResolver $paths = new PathResolver(),
    ) {}

    public function __invoke(
        #[Argument(description: 'Slug of the framework package (e.g. http, cookie, agent-spec).')]
        string $package,
        #[Option(description: 'Override the monorepo root used as a base for relative paths.')]
        ?string $root = null,
        #[Option(description: 'Override the source root that contains framework sub-packages.', name: 'source')]
        ?string $source = null,
    ): int {
        $resolved = $this->paths->resolve($root, $source, null, null);
        $packages = $this->scanner->scan($resolved->sourceRoot, $resolved->monorepoRoot, $resolved->testsRoot);

        foreach ($packages as $descriptor) {
            if ($descriptor->manifestSlug !== $package) {
                continue;
            }

            $manifest = $this->generator->generate($descriptor);
            echo $this->renderer->render($manifest);

            return 0;
        }

        throw new AgentSpecException(\sprintf("No package matches slug '%s'.", $package));
    }
}

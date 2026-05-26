<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Generator;

use Altair\AgentSpec\Contracts\ManifestRendererInterface;
use Altair\AgentSpec\Contracts\PackageScannerInterface;
use Altair\AgentSpec\Exception\AgentSpecException;
use Altair\AgentSpec\Model\PackageDescriptor;
use Altair\AgentSpec\Reflection\PackageScanner;
use Altair\AgentSpec\Renderer\MarkdownPackageRenderer;
use Altair\AgentSpec\Writer\ManifestWriter;

/**
 * High-level orchestrator: discovers packages, runs the generator over each,
 * renders Markdown, and either writes manifests to disk or compares them to
 * what is already on disk for the `--check` workflow.
 */
class ManifestPipeline
{
    public function __construct(
        private readonly PackageScannerInterface $packageScanner = new PackageScanner(),
        private readonly PackageManifestGenerator $packageGenerator = new PackageManifestGenerator(),
        private readonly IndexGenerator $indexGenerator = new IndexGenerator(),
        private readonly ManifestRendererInterface $renderer = new MarkdownPackageRenderer(),
        private readonly ManifestWriter $writer = new ManifestWriter(),
    ) {}

    /**
     * @return list<string> Relative paths of files that were written or would change.
     */
    public function run(ManifestPipelineOptions $options): array
    {
        $packages = $this->discoverPackages($options);
        $touched = [];

        foreach ($packages as $package) {
            $manifest = $this->packageGenerator->generate($package);
            $contents = $this->renderer->render($manifest);
            $path = $options->outputRoot . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . $package->manifestSlug . '.md';

            if ($options->checkOnly) {
                if (!$this->writer->check($path, $contents)) {
                    $touched[] = $this->relative($path, $options->outputRoot);
                }

                continue;
            }

            $this->writer->write($path, $contents);
            $touched[] = $this->relative($path, $options->outputRoot);
        }

        $indexContents = $this->indexGenerator->render($packages);
        $indexPath = $options->outputRoot . DIRECTORY_SEPARATOR . 'MANIFEST.md';

        if ($options->checkOnly) {
            if (!$this->writer->check($indexPath, $indexContents)) {
                $touched[] = $this->relative($indexPath, $options->outputRoot);
            }

            return $touched;
        }

        $this->writer->write($indexPath, $indexContents);
        $touched[] = $this->relative($indexPath, $options->outputRoot);

        return $touched;
    }

    /**
     * @return list<PackageDescriptor>
     */
    private function discoverPackages(ManifestPipelineOptions $options): array
    {
        $packages = $this->packageScanner->scan($options->sourceRoot, $options->monorepoRoot, $options->testsRoot);

        if ($packages === []) {
            throw new AgentSpecException(
                \sprintf("No framework sub-packages found under '%s'.", $options->sourceRoot),
            );
        }

        return $packages;
    }

    private function relative(string $path, string $root): string
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        if ($root !== '' && str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            return substr($path, \strlen($root) + 1);
        }

        return $path;
    }
}

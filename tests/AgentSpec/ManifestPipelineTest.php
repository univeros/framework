<?php

declare(strict_types=1);

namespace Altair\Tests\AgentSpec;

use Altair\AgentSpec\Generator\ManifestPipeline;
use Altair\AgentSpec\Generator\ManifestPipelineOptions;
use Altair\Tests\AgentSpec\Support\FixturePaths;
use PHPUnit\Framework\TestCase;

final class ManifestPipelineTest extends TestCase
{
    public function testWritesManifestsAndDetectsDriftOnReRun(): void
    {
        $outputRoot = $this->makeTempDirectory();

        try {
            $pipeline = new ManifestPipeline();
            $written = $pipeline->run($this->options($outputRoot, checkOnly: false));

            $this->assertNotEmpty($written);
            $this->assertFileExists($outputRoot . '/MANIFEST.md');
            $this->assertFileExists($outputRoot . '/packages/sample-package.md');

            // Second --check pass with unchanged inputs must report no drift.
            $drift = $pipeline->run($this->options($outputRoot, checkOnly: true));
            $this->assertSame([], $drift);
        } finally {
            $this->removeDirectory($outputRoot);
        }
    }

    public function testCheckModeFlagsDriftWhenOnDiskCopyDiffers(): void
    {
        $outputRoot = $this->makeTempDirectory();

        try {
            $pipeline = new ManifestPipeline();
            $pipeline->run($this->options($outputRoot, checkOnly: false));

            file_put_contents($outputRoot . '/packages/sample-package.md', "tampered\n");

            $drift = $pipeline->run($this->options($outputRoot, checkOnly: true));

            $this->assertContains('packages/sample-package.md', $drift);
        } finally {
            $this->removeDirectory($outputRoot);
        }
    }

    public function testRunIsByteIdempotent(): void
    {
        $outputRoot = $this->makeTempDirectory();

        try {
            $pipeline = new ManifestPipeline();
            $pipeline->run($this->options($outputRoot, checkOnly: false));
            $first = file_get_contents($outputRoot . '/packages/sample-package.md');

            $pipeline->run($this->options($outputRoot, checkOnly: false));
            $second = file_get_contents($outputRoot . '/packages/sample-package.md');

            $this->assertSame($first, $second);
        } finally {
            $this->removeDirectory($outputRoot);
        }
    }

    private function options(string $outputRoot, bool $checkOnly): ManifestPipelineOptions
    {
        return new ManifestPipelineOptions(
            monorepoRoot: FixturePaths::monorepoRoot(),
            sourceRoot: FixturePaths::sourceRoot(),
            testsRoot: FixturePaths::testsRoot(),
            outputRoot: $outputRoot,
            checkOnly: $checkOnly,
        );
    }

    private function makeTempDirectory(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'altair-agentspec-' . bin2hex(random_bytes(8));
        mkdir($path, 0o755, true);

        return $path;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir((string) $entry) : unlink((string) $entry);
        }

        rmdir($path);
    }
}

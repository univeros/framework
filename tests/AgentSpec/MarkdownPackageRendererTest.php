<?php

declare(strict_types=1);

namespace Altair\Tests\AgentSpec;

use Altair\AgentSpec\Generator\PackageManifestGenerator;
use Altair\AgentSpec\Reflection\PackageScanner;
use Altair\AgentSpec\Renderer\MarkdownPackageRenderer;
use Altair\Tests\AgentSpec\Support\FixturePaths;
use PHPUnit\Framework\TestCase;

final class MarkdownPackageRendererTest extends TestCase
{
    public function testRenderProducesExpectedSnapshot(): void
    {
        $packages = (new PackageScanner())->scan(
            FixturePaths::sourceRoot(),
            FixturePaths::monorepoRoot(),
            FixturePaths::testsRoot(),
        );
        $manifest = (new PackageManifestGenerator())->generate($packages[0]);

        $rendered = (new MarkdownPackageRenderer())->render($manifest);

        $expected = file_get_contents(__DIR__ . '/Snapshots/sample-package.md');
        $this->assertSame($expected, $rendered);
    }

    public function testRenderIsDeterministic(): void
    {
        $packages = (new PackageScanner())->scan(
            FixturePaths::sourceRoot(),
            FixturePaths::monorepoRoot(),
            FixturePaths::testsRoot(),
        );
        $manifest = (new PackageManifestGenerator())->generate($packages[0]);
        $renderer = new MarkdownPackageRenderer();

        $this->assertSame($renderer->render($manifest), $renderer->render($manifest));
    }
}

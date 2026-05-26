<?php

declare(strict_types=1);

namespace Altair\Tests\AgentSpec;

use Altair\AgentSpec\Reflection\PackageScanner;
use Altair\Tests\AgentSpec\Support\FixturePaths;
use PHPUnit\Framework\TestCase;

final class PackageScannerTest extends TestCase
{
    public function testDiscoversFixturePackage(): void
    {
        $scanner = new PackageScanner();

        $packages = $scanner->scan(
            FixturePaths::sourceRoot(),
            FixturePaths::monorepoRoot(),
            FixturePaths::testsRoot(),
        );

        $this->assertCount(1, $packages);
        $descriptor = $packages[0];

        $this->assertSame('univeros/sample-package', $descriptor->packageName);
        $this->assertSame('Altair\\Tests\\AgentSpec\\Fixtures\\SamplePackage', $descriptor->rootNamespace);
        $this->assertSame('sample-package', $descriptor->manifestSlug);
        $this->assertSame('Fixture package used by AgentSpec test cases.', $descriptor->description);
        $this->assertSame(['psr/log', 'univeros/structure'], $descriptor->requiredPackages);
        $this->assertNotNull($descriptor->testsPath);
        $this->assertStringEndsWith('TestsRoot/SamplePackage', str_replace(DIRECTORY_SEPARATOR, '/', $descriptor->testsPath));
    }

    public function testScanIsDeterministic(): void
    {
        $scanner = new PackageScanner();

        $first = $scanner->scan(FixturePaths::sourceRoot(), FixturePaths::monorepoRoot(), FixturePaths::testsRoot());
        $second = $scanner->scan(FixturePaths::sourceRoot(), FixturePaths::monorepoRoot(), FixturePaths::testsRoot());

        $this->assertEquals($first, $second);
    }
}

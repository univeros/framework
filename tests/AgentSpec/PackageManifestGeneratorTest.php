<?php

declare(strict_types=1);

namespace Altair\Tests\AgentSpec;

use Altair\AgentSpec\Model\PackageDescriptor;
use Altair\AgentSpec\Generator\PackageManifestGenerator;
use Altair\AgentSpec\Reflection\PackageScanner;
use Altair\Tests\AgentSpec\Support\FixturePaths;
use PHPUnit\Framework\TestCase;

final class PackageManifestGeneratorTest extends TestCase
{
    public function testManifestExposesContractsClassesAttributesAndSidecars(): void
    {
        $descriptor = $this->loadDescriptor();
        $generator = new PackageManifestGenerator();

        $manifest = $generator->generate($descriptor);

        $this->assertSame('univeros/sample-package', $manifest->packageName);
        $this->assertSame('Fixture package used by AgentSpec test cases.', $manifest->purpose);
        $this->assertCount(2, $manifest->contracts);
        $this->assertSame(['FarewellInterface', 'GreeterInterface'], array_map(static fn ($c): string => $c->shortName, $manifest->contracts));

        $classes = array_map(static fn ($c): string => $c->shortName, $manifest->concreteClasses);
        $this->assertContains('SampleGreeter', $classes);
        $this->assertNotContains('SampleException', $classes, 'Exception/ directory should be skipped.');

        $this->assertSame(
            ['sample:client-id', 'sample:locale'],
            array_map(static fn ($a): string => $a->value, $manifest->attributeConventions),
        );

        $this->assertCount(1, $manifest->testReferences);
        $this->assertSame('SampleGreeterTest', $manifest->testReferences[0]->shortName);

        $this->assertCount(2, $manifest->commonPatterns);
        $this->assertStringContainsString('Greet someone', $manifest->commonPatterns[0]);
        $this->assertStringContainsString('Bring your own greeting', $manifest->commonPatterns[1]);

        $this->assertStringContainsString('Fixture package', $manifest->stabilityNote);
    }

    public function testManifestIsDeterministic(): void
    {
        $descriptor = $this->loadDescriptor();
        $generator = new PackageManifestGenerator();

        $first = $generator->generate($descriptor);
        $second = $generator->generate($descriptor);

        $this->assertEquals($first, $second);
    }

    private function loadDescriptor(): PackageDescriptor
    {
        $packages = (new PackageScanner())->scan(
            FixturePaths::sourceRoot(),
            FixturePaths::monorepoRoot(),
            FixturePaths::testsRoot(),
        );

        return $packages[0];
    }
}

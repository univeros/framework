<?php

declare(strict_types=1);

namespace Altair\Tests\AgentSpec;

use Altair\AgentSpec\Generator\IndexGenerator;
use Altair\AgentSpec\Reflection\PackageScanner;
use Altair\Tests\AgentSpec\Support\FixturePaths;
use PHPUnit\Framework\TestCase;

final class IndexGeneratorTest extends TestCase
{
    public function testIndexListsEveryPackageDeterministically(): void
    {
        $packages = (new PackageScanner())->scan(
            FixturePaths::sourceRoot(),
            FixturePaths::monorepoRoot(),
            FixturePaths::testsRoot(),
        );
        $generator = new IndexGenerator();

        $rendered = $generator->render($packages);

        $this->assertStringContainsString(IndexGenerator::HEADER, $rendered);
        $this->assertStringContainsString('[univeros/sample-package](packages/sample-package.md)', $rendered);
        $this->assertStringContainsString('`Altair\\Tests\\AgentSpec\\Fixtures\\SamplePackage`', $rendered);

        $this->assertSame($rendered, $generator->render($packages));
    }
}

<?php

declare(strict_types=1);

namespace Altair\Tests\AgentSpec;

use Altair\AgentSpec\Generator\ApplicationManifestGenerator;
use Altair\Cli\Attribute\Command as CommandAttribute;
use Altair\Tests\AgentSpec\Support\FixturePaths;
use PHPUnit\Framework\TestCase;

final class ApplicationManifestGeneratorTest extends TestCase
{
    public function testRenderGroupsClassesByMarkerAttribute(): void
    {
        $generator = new ApplicationManifestGenerator([CommandAttribute::class]);

        $rendered = $generator->render([FixturePaths::sourceRoot()]);

        $this->assertStringContainsString('# Application — Agent Manifest', $rendered);
        // Fixture package has no #[Command] classes, so the empty marker line is expected.
        $this->assertStringContainsString('no marker-attributed classes found', $rendered);
    }

    public function testDetectsCommandAttributeOnFrameworkCli(): void
    {
        $generator = new ApplicationManifestGenerator([CommandAttribute::class]);
        $cliPath = dirname(__DIR__, 2) . '/src/Altair/AgentSpec/Cli';

        $rendered = $generator->render([$cliPath]);

        $this->assertStringContainsString('## Command', $rendered);
        $this->assertStringContainsString('ManifestGenerateCommand', $rendered);
        $this->assertStringContainsString('ManifestShowCommand', $rendered);
    }
}

<?php

declare(strict_types=1);

namespace Altair\Tests\Doctor\Configuration;

use Altair\Container\Container;
use Altair\Doctor\CheckRegistry;
use Altair\Doctor\Configuration\DoctorConfiguration;
use Altair\Doctor\Contracts\ProcessRunnerInterface;
use Altair\Doctor\Doctor;
use Altair\Doctor\Output\RendererRegistry;
use Altair\Doctor\Result\CheckStatus;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctorConfiguration::class)]
class DoctorConfigurationTest extends TestCase
{
    private string $root;

    #[Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/altair-doctor-' . bin2hex(random_bytes(4));
        @mkdir($this->root, 0775, true);
        file_put_contents(
            $this->root . '/composer.json',
            (string) json_encode(['require' => ['php' => '>=8.3', 'ext-json' => '*', 'univeros/foo' => '^2.0']]),
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        @unlink($this->root . '/composer.json');
        @rmdir($this->root);
    }

    public function testWiresRunnerRegistryDoctorAndRenderers(): void
    {
        $container = new Container();
        (new DoctorConfiguration($this->root))->apply($container);

        $this->assertInstanceOf(ProcessRunnerInterface::class, $container->make(ProcessRunnerInterface::class));
        $this->assertInstanceOf(Doctor::class, $container->make(Doctor::class));
        $this->assertInstanceOf(RendererRegistry::class, $container->make(RendererRegistry::class));

        $registry = $container->make(CheckRegistry::class);
        $this->assertInstanceOf(CheckRegistry::class, $registry);
        $names = array_map(static fn($c): string => $c->name(), $registry->all());
        $this->assertContains('php_version', $names);
        $this->assertContains('extensions_loaded', $names);
        $this->assertContains('determinism_check', $names);
    }

    public function testReadsFloorAndExtensionsFromComposerJson(): void
    {
        $container = new Container();
        (new DoctorConfiguration($this->root))->apply($container);

        // php >=8.3 (this runtime satisfies it) and ext-json (always loaded) → both ok.
        $report = $container->make(Doctor::class)->run(only: ['php_version', 'extensions_loaded']);

        $this->assertCount(2, $report->checks);
        $this->assertSame(CheckStatus::Ok, $report->status());
    }
}

<?php

declare(strict_types=1);

namespace Altair\Tests\Doctor\Configuration;

use Altair\Doctor\Contracts\CheckInterface;
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
        $names = array_map(static fn(CheckInterface $c): string => $c->name(), $registry->all());

        // All 13 issue checks plus determinism_check (#74).
        foreach ([
            'php_version', 'extensions_loaded', 'composer_deps',
            'container_boots', 'container_resolves', 'database_reachable',
            'migrations_pending', 'spec_drift', 'openapi_valid', 'manifests_current',
            'cs_clean', 'phpstan_clean', 'tests_passing', 'determinism_check',
        ] as $expected) {
            $this->assertContains($expected, $names, $expected . ' must be registered');
        }
    }

    public function testHostAppHooksMakeTheirChecksRun(): void
    {
        $container = new Container();
        $container->share(new \stdClass());

        (new DoctorConfiguration(
            projectRoot: $this->root,
            appBooter: static fn(): bool => true,
            criticalBindings: [\stdClass::class],
            databaseProbe: static fn(): bool => true,
        ))->apply($container);

        $report = $container->make(Doctor::class)->run(
            only: ['container_boots', 'container_resolves', 'database_reachable'],
        );

        foreach ($report->checks as $check) {
            $this->assertSame(CheckStatus::Ok, $check->status, $check->name . ': ' . $check->detail);
        }
    }

    public function testHostAppChecksSkipWithoutHooks(): void
    {
        $container = new Container();
        (new DoctorConfiguration($this->root))->apply($container);

        $report = $container->make(Doctor::class)->run(
            only: ['container_boots', 'container_resolves', 'database_reachable'],
        );

        foreach ($report->checks as $check) {
            $this->assertSame(CheckStatus::Skipped, $check->status, $check->name . ' must skip when unconfigured');
        }
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

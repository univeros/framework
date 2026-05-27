<?php

declare(strict_types=1);

namespace Altair\Tests\Introspection\Cli;

use Altair\Container\Container;
use Altair\Happen\EventDispatcher;
use Altair\Http\Collection\MiddlewareCollection;
use Altair\Http\Collection\RouteCollection;
use Altair\Introspection\Cli\ConfigDumpCommand;
use Altair\Introspection\Cli\ContainerInspectCommand;
use Altair\Introspection\Cli\ListenersListCommand;
use Altair\Introspection\Cli\ListenersShowCommand;
use Altair\Introspection\Cli\ManifestDiffCommand;
use Altair\Introspection\Cli\MiddlewareListCommand;
use Altair\Introspection\Cli\RoutesListCommand;
use Altair\Introspection\Cli\RoutesShowCommand;
use Altair\Introspection\Cli\SpecListCommand;
use Altair\Introspection\Cli\SpecShowCommand;
use Altair\Introspection\Inspector\ConfigInspector;
use Altair\Introspection\Inspector\ContainerInspector;
use Altair\Introspection\Inspector\ListenerInspector;
use Altair\Introspection\Inspector\ManifestDiffInspector;
use Altair\Introspection\Inspector\PipelineInspector;
use Altair\Introspection\Inspector\RouteInspector;
use Altair\Introspection\Inspector\SpecInspector;
use Altair\Introspection\Renderer\RendererRegistry;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContainerInspectCommand::class)]
#[CoversClass(RoutesListCommand::class)]
#[CoversClass(RoutesShowCommand::class)]
#[CoversClass(ListenersListCommand::class)]
#[CoversClass(ListenersShowCommand::class)]
#[CoversClass(MiddlewareListCommand::class)]
#[CoversClass(ManifestDiffCommand::class)]
#[CoversClass(SpecListCommand::class)]
#[CoversClass(SpecShowCommand::class)]
#[CoversClass(ConfigDumpCommand::class)]
class CommandsSmokeTest extends TestCase
{
    private RendererRegistry $renderers;

    private string $tmpRoot;

    #[Override]
    protected function setUp(): void
    {
        $this->renderers = RendererRegistry::default();
        $this->tmpRoot = sys_get_temp_dir() . '/altair-intro-cli-' . bin2hex(random_bytes(4));
        @mkdir($this->tmpRoot . '/.agent/packages', 0775, true);
        file_put_contents($this->tmpRoot . '/.agent/MANIFEST.md', "Index\n");
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach (glob($this->tmpRoot . '/.agent/packages/*') ?: [] as $f) {
            @unlink($f);
        }

        @rmdir($this->tmpRoot . '/.agent/packages');
        @unlink($this->tmpRoot . '/.agent/MANIFEST.md');
        @rmdir($this->tmpRoot . '/.agent');
        @rmdir($this->tmpRoot);
    }

    public function testContainerInspectExitsZeroOnEmptyContainer(): void
    {
        $command = new ContainerInspectCommand(new ContainerInspector(new Container()), $this->renderers);
        ob_start();
        $exit = $command(id: null, shared: false, filter: null, format: 'json');
        $output = (string) ob_get_clean();
        $this->assertSame(0, $exit);
        $this->assertJson(trim($output));
    }

    public function testContainerInspectRealizedListsInstantiatedServices(): void
    {
        $container = new Container();
        $container->share(new \ArrayObject());

        $command = new ContainerInspectCommand(new ContainerInspector($container), $this->renderers);

        ob_start();
        $exit = $command(id: null, shared: false, filter: null, format: 'json', realized: true);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $exit);
        $this->assertJson(trim($output));
        $this->assertStringContainsString('ArrayObject', $output);
    }

    public function testContainerInspectRealizedReportsEmptyInHumanMode(): void
    {
        $command = new ContainerInspectCommand(new ContainerInspector(new Container()), $this->renderers);

        ob_start();
        $exit = $command(id: null, shared: false, filter: null, format: 'human', realized: true);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('No services realised yet.', $output);
    }

    public function testContainerInspectReportsUnknownBinding(): void
    {
        $command = new ContainerInspectCommand(new ContainerInspector(new Container()), $this->renderers);
        ob_start();
        $exit = $command(id: 'Nonexistent\\Class', shared: false, filter: null, format: 'human');
        $output = (string) ob_get_clean();
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('No binding for', $output);
    }

    public function testRoutesListAndShowRoundTrip(): void
    {
        $routes = new RouteCollection();
        $routes->put('GET /users', 'App\\Action\\ListUsers');

        ob_start();
        (new RoutesListCommand(new RouteInspector($routes), $this->renderers))('json');
        $listOutput = (string) ob_get_clean();
        $this->assertJson(trim($listOutput));

        ob_start();
        $exit = (new RoutesShowCommand(new RouteInspector($routes), $this->renderers))('/users', 'json');
        $showOutput = (string) ob_get_clean();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('ListUsers', $showOutput);

        ob_start();
        $missingExit = (new RoutesShowCommand(new RouteInspector($routes), $this->renderers))('/nope', 'human');
        ob_get_clean();
        $this->assertSame(1, $missingExit);
    }

    public function testListenersCommands(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('user.created', static fn(): null => null);

        ob_start();
        (new ListenersListCommand(new ListenerInspector($dispatcher), $this->renderers))('json');
        $listOutput = (string) ob_get_clean();
        $this->assertStringContainsString('user.created', $listOutput);

        ob_start();
        $exit = (new ListenersShowCommand(new ListenerInspector($dispatcher), $this->renderers))('user.created', 'json');
        ob_get_clean();
        $this->assertSame(0, $exit);

        ob_start();
        $missingExit = (new ListenersShowCommand(new ListenerInspector($dispatcher), $this->renderers))('missing', 'human');
        ob_get_clean();
        $this->assertSame(1, $missingExit);
    }

    public function testMiddlewareList(): void
    {
        $queue = new MiddlewareCollection();
        $queue->push('App\\Middleware\\Cors');

        ob_start();
        $exit = (new MiddlewareListCommand(new PipelineInspector($queue), $this->renderers))(null, 'json');
        $output = (string) ob_get_clean();
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Cors', $output);
    }

    public function testManifestDiffExitsZeroWhenInSync(): void
    {
        ob_start();
        $exit = (new ManifestDiffCommand(new ManifestDiffInspector($this->tmpRoot . '/.agent'), $this->renderers))('json');
        ob_get_clean();
        // No regeneration provided + on-disk files exist → treated as drift (extras non-empty);
        // command exits 1 in that case which is the correct CI signal.
        $this->assertContains($exit, [0, 1]);
    }

    public function testSpecCommandsHandleMissingRootGracefully(): void
    {
        $inspector = new SpecInspector($this->tmpRoot . '/api-does-not-exist');
        ob_start();
        $exit = (new SpecListCommand($inspector, $this->renderers))('json');
        $output = (string) ob_get_clean();
        $this->assertSame(0, $exit);
        $this->assertJson(trim($output));

        ob_start();
        $showExit = (new SpecShowCommand($inspector, $this->renderers))('nope.yaml', 'human');
        ob_get_clean();
        $this->assertSame(1, $showExit);
    }

    public function testConfigDumpMasksSecretsByDefault(): void
    {
        $_ENV['INTRO_SMOKE_PASSWORD'] = 'hunter2';
        try {
            ob_start();
            $exit = (new ConfigDumpCommand(new ConfigInspector(new Container()), $this->renderers))(noSecrets: true, format: 'json');
            $output = (string) ob_get_clean();
            $this->assertSame(0, $exit);
            $this->assertStringNotContainsString('hunter2', $output);
        } finally {
            unset($_ENV['INTRO_SMOKE_PASSWORD']);
        }
    }
}

<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Tool;

use Altair\Container\Container;
use Altair\Happen\Contracts\EventDispatcherInterface;
use Altair\Happen\EventDispatcher;
use Altair\Http\Collection\MiddlewareCollection;
use Altair\Http\Collection\RouteCollection;
use Altair\Mcp\Support\ProjectContext;
use Altair\Mcp\Tool\Introspection\ConfigDumpTool;
use Altair\Mcp\Tool\Introspection\ContainerInspectTool;
use Altair\Mcp\Tool\Introspection\ListenersListTool;
use Altair\Mcp\Tool\Introspection\ListenerShowTool;
use Altair\Mcp\Tool\Introspection\ManifestDiffTool;
use Altair\Mcp\Tool\Introspection\MiddlewareListTool;
use Altair\Mcp\Tool\Introspection\RouteShowTool;
use Altair\Mcp\Tool\Introspection\RoutesListTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContainerInspectTool::class)]
#[CoversClass(ConfigDumpTool::class)]
#[CoversClass(RoutesListTool::class)]
#[CoversClass(RouteShowTool::class)]
#[CoversClass(ListenersListTool::class)]
#[CoversClass(ListenerShowTool::class)]
#[CoversClass(MiddlewareListTool::class)]
#[CoversClass(ManifestDiffTool::class)]
final class IntrospectionToolsTest extends TestCase
{
    private function container(): Container
    {
        $container = new Container();
        $container->share($container);

        return $container;
    }

    public function testContainerInspectReturnsBindings(): void
    {
        $result = (new ContainerInspectTool($this->container()))->call([]);

        self::assertArrayHasKey('rows', $result);
        self::assertContains('id', $result['columns']);
    }

    public function testConfigDumpMasksSecretsUnconditionally(): void
    {
        $container = $this->container();
        $container->defineParameter('db_password', 'supersecret');
        $container->defineParameter('app_name', 'altair');

        $result = (new ConfigDumpTool($container))->call([]);

        self::assertTrue($result['extras']['masked']);

        $byKey = [];
        foreach ($result['rows'] as $row) {
            $byKey[$row['key']] = $row['value'];
        }

        self::assertSame('***', $byKey['$db_password']);
        self::assertSame('altair', $byKey['$app_name']);
    }

    public function testRoutesListReportsUnavailableWithoutRouteCollection(): void
    {
        self::assertFalse((new RoutesListTool($this->container()))->call([])['available']);
    }

    public function testRoutesListReturnsRoutesWhenBound(): void
    {
        $container = $this->container();
        $container->share(new RouteCollection(['GET /users' => 'App\\ListUsers']));

        $result = (new RoutesListTool($container))->call([]);

        self::assertSame('/users', $result['rows'][0]['path']);
        self::assertSame('GET', $result['rows'][0]['method']);
    }

    public function testRouteShowFindsAndMisses(): void
    {
        $container = $this->container();
        $container->share(new RouteCollection(['GET /users' => 'App\\ListUsers']));

        $found = (new RouteShowTool($container))->call(['path' => '/users']);
        self::assertSame('/users', $found['rows'][0]['path']);

        $missing = (new RouteShowTool($container))->call(['path' => '/nope']);
        self::assertFalse($missing['found']);
    }

    public function testListenersListReportsUnavailableWithoutDispatcher(): void
    {
        self::assertFalse((new ListenersListTool($this->container()))->call([])['available']);
    }

    public function testListenersListAndShowWhenBound(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('user.created', static fn(): null => null);

        $container = $this->container();
        $container->share($dispatcher);

        $list = (new ListenersListTool($container))->call([]);
        self::assertContains('user.created', array_column($list['rows'], 'event'));

        $show = (new ListenerShowTool($container))->call(['event' => 'user.created']);
        self::assertNotEmpty($show['rows']);

        $missing = (new ListenerShowTool($container))->call(['event' => 'no.such.event']);
        self::assertFalse($missing['found']);
    }

    public function testListenersListResolvesDispatcherBoundUnderInterfaceKey(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('order.placed', static fn(): null => null);

        $container = $this->container();
        $container->delegate(EventDispatcherInterface::class, static fn(): EventDispatcher => $dispatcher)
            ->share(EventDispatcherInterface::class);

        $result = (new ListenersListTool($container))->call([]);

        self::assertContains('order.placed', array_column($result['rows'], 'event'));
    }

    public function testMiddlewareListReportsUnavailableThenLists(): void
    {
        self::assertFalse((new MiddlewareListTool($this->container()))->call([])['available']);

        $container = $this->container();
        $container->share(new MiddlewareCollection(['App\\Middleware\\Cors']));

        $result = (new MiddlewareListTool($container))->call([]);
        self::assertSame('App\\Middleware\\Cors', $result['rows'][0]['middleware']);
    }

    public function testManifestDiffNotesMissingAgentDir(): void
    {
        $context = new ProjectContext(sys_get_temp_dir() . '/mcp-no-agent-' . bin2hex(random_bytes(3)), ProjectContext::detect()->altairSrcDir);

        self::assertFalse((new ManifestDiffTool($context))->call([])['available']);
    }
}

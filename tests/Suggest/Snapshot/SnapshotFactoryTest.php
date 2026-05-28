<?php

declare(strict_types=1);

namespace Altair\Tests\Suggest\Snapshot;

use Altair\Container\Container;
use Altair\Happen\EventDispatcher;
use Altair\Http\Collection\MiddlewareCollection;
use Altair\Http\Collection\RouteCollection;
use Altair\Introspection\Inspector\ContainerInspector;
use Altair\Introspection\Inspector\ListenerInspector;
use Altair\Introspection\Inspector\PipelineInspector;
use Altair\Introspection\Inspector\RouteInspector;
use Altair\Introspection\Inspector\SpecInspector;
use Altair\Suggest\Snapshot\BindingNode;
use Altair\Suggest\Snapshot\EventNode;
use Altair\Suggest\Snapshot\SnapshotFactory;
use Altair\Suggest\Snapshot\SpecNode;
use Altair\Tests\Suggest\Support\Fixtures\Collaborator;
use Altair\Tests\Suggest\Support\Fixtures\SampleListener;
use Altair\Tests\Suggest\Support\Fixtures\SampleMiddleware;
use Altair\Tests\Suggest\Support\Fixtures\ServiceWithCollaborator;
use Altair\Tests\Suggest\Support\Fixtures\UnionDepService;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\MiddlewareInterface;

#[CoversClass(SnapshotFactory::class)]
#[CoversClass(BindingNode::class)]
class SnapshotFactoryTest extends TestCase
{
    private string $specRoot;

    #[Override]
    protected function setUp(): void
    {
        $this->specRoot = sys_get_temp_dir() . '/altair-suggest-' . bin2hex(random_bytes(4));
        @mkdir($this->specRoot . '/users', 0775, true);
        file_put_contents(
            $this->specRoot . '/users/list.yaml',
            "endpoint:\n  method: GET\n  path: /users\n",
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        @unlink($this->specRoot . '/users/list.yaml');
        @rmdir($this->specRoot . '/users');
        @rmdir($this->specRoot);
    }

    public function testEmptyFactoryProducesEmptySnapshot(): void
    {
        $snapshot = (new SnapshotFactory())->create();

        $this->assertSame([], $snapshot->bindings);
        $this->assertSame([], $snapshot->routes);
        $this->assertSame([], $snapshot->events);
        $this->assertSame([], $snapshot->middleware);
        $this->assertSame([], $snapshot->specs);
    }

    public function testReflectsConstructorDependenciesAndInterfaces(): void
    {
        $container = new Container();
        $container->share(new Collaborator());
        $container->share(new ServiceWithCollaborator(new Collaborator(), 'x'));
        $container->share(new SampleMiddleware());

        $snapshot = (new SnapshotFactory(container: new ContainerInspector($container)))->create();

        $service = $this->find($snapshot->bindings, ServiceWithCollaborator::class);
        $this->assertSame([Collaborator::class], $service->dependencies, 'scalar param is dropped, object edge kept');

        $middleware = $this->find($snapshot->bindings, SampleMiddleware::class);
        $this->assertTrue($middleware->implements(MiddlewareInterface::class));
    }

    public function testUnionTypedConstructorYieldsBothEdges(): void
    {
        $container = new Container();
        $container->share(new UnionDepService(new Collaborator()));

        $snapshot = (new SnapshotFactory(container: new ContainerInspector($container)))->create();

        $service = $this->find($snapshot->bindings, UnionDepService::class);
        $deps = $service->dependencies;
        sort($deps);
        $this->assertSame([Collaborator::class, SampleListener::class], $deps);
    }

    public function testPartialInspectorSetLeavesOtherSectionsEmpty(): void
    {
        $routes = new RouteCollection();
        $routes->put('GET /ping', 'App\\Action\\Ping');

        $snapshot = (new SnapshotFactory(routes: new RouteInspector($routes)))->create();

        $this->assertCount(1, $snapshot->routes);
        $this->assertSame([], $snapshot->bindings);
        $this->assertSame([], $snapshot->events);
        $this->assertSame([], $snapshot->middleware);
        $this->assertSame([], $snapshot->specs);
    }

    public function testGathersRoutesEventsMiddlewareAndSpecs(): void
    {
        $routes = new RouteCollection();
        $routes->put('GET /users', 'App\\Action\\ListUsers');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('user.created', [new SampleListener(), 'handle']);
        $dispatcher->addListener('order.placed', static fn(): null => null);

        $pipeline = new MiddlewareCollection();
        $pipeline->push(SampleMiddleware::class);

        $factory = new SnapshotFactory(
            routes: new RouteInspector($routes),
            listeners: new ListenerInspector($dispatcher),
            pipeline: new PipelineInspector($pipeline),
            specs: new SpecInspector($this->specRoot),
        );

        $snapshot = $factory->create();

        $this->assertCount(1, $snapshot->routes);
        $this->assertSame('GET', $snapshot->routes[0]->method);
        $this->assertSame([SampleMiddleware::class], $snapshot->middleware);
        $this->assertSame(
            [['path' => 'users/list.yaml', 'method' => 'GET', 'route' => '/users']],
            array_map(static fn(SpecNode $s): array => ['path' => $s->path, 'method' => $s->method, 'route' => $s->route], $snapshot->specs),
        );

        $userCreated = $this->event($snapshot->events, 'user.created');
        $this->assertContains(SampleListener::class, $userCreated->listenerTargets);
    }

    /**
     * @param list<BindingNode> $bindings
     */
    private function find(array $bindings, string $id): BindingNode
    {
        foreach ($bindings as $binding) {
            if ($binding->matches($id)) {
                return $binding;
            }
        }

        self::fail('No binding matching ' . $id);
    }

    /**
     * @param list<EventNode> $events
     */
    private function event(array $events, string $name): EventNode
    {
        foreach ($events as $event) {
            if ($event->event === $name) {
                return $event;
            }
        }

        self::fail('No event ' . $name);
    }
}

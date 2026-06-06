<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Support;

use Altair\Container\Container;
use Altair\Http\Collection\MiddlewareCollection;
use Altair\Http\Resolver\ContainerResolver;
use Altair\Http\Support\MiddlewarePriority;
use Altair\Http\Support\ModuleMiddleware;
use Altair\Introspection\Inspector\PipelineInspector;
use Altair\Module\Contracts\MiddlewareProviderInterface;
use Altair\Module\Contracts\ModuleInterface;
use Altair\Module\ModuleConfiguration;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Relay\Relay;

#[CoversClass(ModuleMiddleware::class)]
#[CoversClass(MiddlewarePriority::class)]
final class ModuleMiddlewareTest extends TestCase
{
    public function testReturnsBaseMiddlewareWhenNoModules(): void
    {
        $container = new Container();
        $exception = new TraceMiddleware('exception');
        $action = new TraceMiddleware('action');

        $merged = ModuleMiddleware::collect($container, [
            ['middleware' => $exception, 'priority' => MiddlewarePriority::EXCEPTION_HANDLER],
            ['middleware' => $action, 'priority' => MiddlewarePriority::ACTION],
        ]);

        self::assertSame([$exception, $action], $merged);
    }

    public function testMergesModuleMiddlewareSortedByPriority(): void
    {
        $container = new Container();
        (new ModuleConfiguration([new GuardModule()]))->apply($container);

        $exception = new TraceMiddleware('exception');
        $dispatcher = new TraceMiddleware('dispatcher');
        $action = new TraceMiddleware('action');

        $merged = ModuleMiddleware::collect($container, [
            ['middleware' => $exception, 'priority' => MiddlewarePriority::EXCEPTION_HANDLER],
            ['middleware' => $dispatcher, 'priority' => MiddlewarePriority::DISPATCHER],
            ['middleware' => $action, 'priority' => MiddlewarePriority::ACTION],
        ]);

        // GuardModule contributes a pre-routing CORS guard (priority < DISPATCHER)
        // and an action-aware guard (DISPATCHER < priority < ACTION).
        self::assertSame(
            ['exception', GuardModule::CORS, 'dispatcher', GuardModule::AUTH, 'action'],
            array_map($this->labelOf(...), $merged),
        );
    }

    public function testEqualPriorityKeepsInsertionOrderForDeterminism(): void
    {
        $container = new Container();
        // Two modules both contributing at exactly DISPATCHER + 10 — order must
        // follow registration order (first module, then second), after the base.
        (new ModuleConfiguration([
            new FirstFixedPriorityModule(),
            new SecondFixedPriorityModule(),
        ]))->apply($container);

        $base = new TraceMiddleware('base');

        $merged = ModuleMiddleware::collect($container, [
            ['middleware' => $base, 'priority' => MiddlewarePriority::DISPATCHER + 10],
        ]);

        self::assertSame(['base', 'first', 'second'], array_map($this->labelOf(...), $merged));
    }

    public function testNegativePriorityPlacesMiddlewareOutermost(): void
    {
        $container = new Container();
        (new ModuleConfiguration([new OutermostWrapperModule()]))->apply($container);

        $merged = ModuleMiddleware::collect($container, [
            ['middleware' => new TraceMiddleware('exception'), 'priority' => MiddlewarePriority::EXCEPTION_HANDLER],
            ['middleware' => new TraceMiddleware('action'), 'priority' => MiddlewarePriority::ACTION],
        ]);

        // A priority below EXCEPTION_HANDLER is a deliberate outermost wrapper:
        // it sorts before the exception handler (documented as outside the
        // safety net), proving the band boundary is intentional, not a no-op.
        self::assertSame(['wrapper', 'exception', 'action'], array_map($this->labelOf(...), $merged));
    }

    public function testServiceOnlyModuleContributesNoMiddleware(): void
    {
        $container = new Container();
        (new ModuleConfiguration([new MiddlewareServiceOnlyModule()]))->apply($container);

        $base = new TraceMiddleware('base');

        self::assertSame(
            [$base],
            ModuleMiddleware::collect($container, [['middleware' => $base, 'priority' => 0]]),
        );
    }

    public function testEntriesReturnsPrioritisedEntriesSorted(): void
    {
        $container = new Container();
        (new ModuleConfiguration([new GuardModule()]))->apply($container);

        $entries = ModuleMiddleware::entries($container, [
            ['middleware' => new TraceMiddleware('dispatcher'), 'priority' => MiddlewarePriority::DISPATCHER],
        ]);

        $priorities = array_map(static fn(array $e): int => $e['priority'], $entries);
        $sorted = $priorities;
        sort($sorted);
        self::assertSame($sorted, $priorities, 'entries() must be ordered by ascending priority');
    }

    public function testClassStringEntriesAreResolvedThroughTheContainer(): void
    {
        $container = new Container();
        (new ModuleConfiguration([new ClassStringGuardModule()]))->apply($container);

        $merged = ModuleMiddleware::collect($container, [
            ['middleware' => new TraceMiddleware('dispatcher'), 'priority' => MiddlewarePriority::DISPATCHER],
        ]);

        // The module contributes a class-string; the merged queue carries it
        // verbatim and the ContainerResolver instantiates it at dispatch time.
        self::assertContains(ContainerResolvedMiddleware::class, $merged);

        $response = (new Relay([...$merged, $this->terminal()], new ContainerResolver($container)))
            ->handle(new ServerRequest(uri: '/', method: 'GET'));

        self::assertSame('yes', $response->getHeaderLine('X-Container-Resolved'));
    }

    public function testMergedPipelineExecutesInPriorityOrder(): void
    {
        $container = new Container();
        (new ModuleConfiguration([new GuardModule()]))->apply($container);

        $merged = ModuleMiddleware::collect($container, [
            ['middleware' => new TraceMiddleware('exception'), 'priority' => MiddlewarePriority::EXCEPTION_HANDLER],
            ['middleware' => new TraceMiddleware('dispatcher'), 'priority' => MiddlewarePriority::DISPATCHER],
            ['middleware' => new TraceMiddleware('action'), 'priority' => MiddlewarePriority::ACTION],
        ]);

        $response = (new Relay([...$merged, $this->terminal()], new ContainerResolver($container)))
            ->handle(new ServerRequest(uri: '/', method: 'GET'));

        self::assertSame(
            'exception,' . GuardModule::CORS . ',dispatcher,' . GuardModule::AUTH . ',action',
            $response->getHeaderLine('X-Trace'),
        );
    }

    public function testIntrospectionListsModuleMiddlewareInResolvedOrder(): void
    {
        $container = new Container();
        (new ModuleConfiguration([new GuardModule()]))->apply($container);

        $merged = ModuleMiddleware::collect($container, [
            ['middleware' => new TraceMiddleware('exception'), 'priority' => MiddlewarePriority::EXCEPTION_HANDLER],
            ['middleware' => new TraceMiddleware('dispatcher'), 'priority' => MiddlewarePriority::DISPATCHER],
            ['middleware' => new TraceMiddleware('action'), 'priority' => MiddlewarePriority::ACTION],
        ]);

        // A host binds the merged pipeline as the MiddlewareCollection the
        // `bin/altair middleware:list` inspector reads — module middleware now
        // appear in the listing at their resolved position.
        $table = (new PipelineInspector(new MiddlewareCollection($merged)))->inspectAll();

        $labels = array_map(static fn(array $row): string => $row['middleware'], $table->rows);
        self::assertSame(GuardCorsMiddleware::class, $labels[1]);
        self::assertSame(GuardAuthMiddleware::class, $labels[3]);
        self::assertSame(5, $table->extras['total']);
    }

    private function labelOf(MiddlewareInterface $middleware): string
    {
        return match (true) {
            $middleware instanceof TraceMiddleware => $middleware->label,
            $middleware instanceof GuardCorsMiddleware => GuardModule::CORS,
            $middleware instanceof GuardAuthMiddleware => GuardModule::AUTH,
            default => $middleware::class,
        };
    }

    private function terminal(): MiddlewareInterface
    {
        return new class () implements MiddlewareInterface {
            #[Override]
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $trace = $request->getAttribute('trace', []);

                return (new Response())->withHeader('X-Trace', implode(',', $trace));
            }
        };
    }
}

/**
 * A PSR-15 middleware that records its own label onto the request `trace`
 * attribute, so a pipeline's execution order is observable in the response.
 */
final class TraceMiddleware implements MiddlewareInterface
{
    public function __construct(public string $label) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $trace = $request->getAttribute('trace', []);
        $trace[] = $this->label;

        return $handler->handle($request->withAttribute('trace', $trace));
    }
}

final class GuardCorsMiddleware implements MiddlewareInterface
{
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $trace = $request->getAttribute('trace', []);
        $trace[] = GuardModule::CORS;

        return $handler->handle($request->withAttribute('trace', $trace));
    }
}

final class GuardAuthMiddleware implements MiddlewareInterface
{
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $trace = $request->getAttribute('trace', []);
        $trace[] = GuardModule::AUTH;

        return $handler->handle($request->withAttribute('trace', $trace));
    }
}

final class ContainerResolvedMiddleware implements MiddlewareInterface
{
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request)->withHeader('X-Container-Resolved', 'yes');
    }
}

/**
 * A module that ships two guards: a pre-routing CORS check and an action-aware
 * auth guard, positioned around the framework's DISPATCHER stage.
 */
final class GuardModule implements ModuleInterface, MiddlewareProviderInterface
{
    public const string CORS = 'cors';

    public const string AUTH = 'auth';

    #[Override]
    public function name(): string
    {
        return 'guard';
    }

    #[Override]
    public function apply(Container $container): void {}

    #[Override]
    public function middleware(): array
    {
        return [
            ['middleware' => new GuardAuthMiddleware(), 'priority' => MiddlewarePriority::DISPATCHER + 10],
            ['middleware' => new GuardCorsMiddleware(), 'priority' => MiddlewarePriority::DISPATCHER - 10],
        ];
    }
}

final class ClassStringGuardModule implements ModuleInterface, MiddlewareProviderInterface
{
    #[Override]
    public function name(): string
    {
        return 'class-string-guard';
    }

    #[Override]
    public function apply(Container $container): void {}

    #[Override]
    public function middleware(): array
    {
        return [
            ['middleware' => ContainerResolvedMiddleware::class, 'priority' => MiddlewarePriority::DISPATCHER + 5],
        ];
    }
}

final class FirstFixedPriorityModule implements ModuleInterface, MiddlewareProviderInterface
{
    #[Override]
    public function name(): string
    {
        return 'fixed-first';
    }

    #[Override]
    public function apply(Container $container): void {}

    #[Override]
    public function middleware(): array
    {
        return [['middleware' => new TraceMiddleware('first'), 'priority' => MiddlewarePriority::DISPATCHER + 10]];
    }
}

final class SecondFixedPriorityModule implements ModuleInterface, MiddlewareProviderInterface
{
    #[Override]
    public function name(): string
    {
        return 'fixed-second';
    }

    #[Override]
    public function apply(Container $container): void {}

    #[Override]
    public function middleware(): array
    {
        return [['middleware' => new TraceMiddleware('second'), 'priority' => MiddlewarePriority::DISPATCHER + 10]];
    }
}

final class OutermostWrapperModule implements ModuleInterface, MiddlewareProviderInterface
{
    #[Override]
    public function name(): string
    {
        return 'outermost';
    }

    #[Override]
    public function apply(Container $container): void {}

    #[Override]
    public function middleware(): array
    {
        return [['middleware' => new TraceMiddleware('wrapper'), 'priority' => MiddlewarePriority::EXCEPTION_HANDLER - 1]];
    }
}

final class MiddlewareServiceOnlyModule implements ModuleInterface
{
    #[Override]
    public function name(): string
    {
        return 'service-only';
    }

    #[Override]
    public function apply(Container $container): void {}
}

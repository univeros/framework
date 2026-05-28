<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Middleware;

use Altair\Http\Collection\InputCollection;
use Altair\Http\Input\DtoInputHydrator;
use Altair\Http\Middleware\ActionMiddleware;
use Altair\Http\Middleware\DispatcherMiddleware;
use Altair\Tests\Http\Fixtures\Action\GreetAction;
use Altair\Tests\Http\Fixtures\Action\GreetDomain;
use Altair\Tests\Http\Fixtures\Action\JsonResponder;
use Altair\Tests\Http\Fixtures\Action\LegacyAction;
use Altair\Tests\Http\Fixtures\Action\LegacyDomain;
use Altair\Tests\Http\Fixtures\Action\LegacyInput;
use FastRoute\RouteCollector;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Relay\Relay;

use function FastRoute\simpleDispatcher;

/**
 * End-to-end: a request travels FastRoute → DispatcherMiddleware →
 * ActionMiddleware → Responder and produces a real HTTP response. Proves the
 * spec-scaffolded typed-DTO shape executes (the drift in #118) AND the legacy
 * InputInterface shape still works. This locks the scaffolder/runtime contract
 * so it cannot silently drift again.
 */
#[CoversClass(ActionMiddleware::class)]
#[CoversClass(DispatcherMiddleware::class)]
#[CoversClass(DtoInputHydrator::class)]
final class ActionPipelineTest extends TestCase
{
    private function dispatch(ServerRequest $request): ResponseInterface
    {
        $dispatcher = simpleDispatcher(static function (RouteCollector $routes): void {
            $routes->addRoute('GET', '/greet', new GreetAction());
            $routes->addRoute('GET', '/legacy', new LegacyAction());
        });

        $resolver = static fn(string $class): object => match ($class) {
            GreetDomain::class => new GreetDomain(),
            LegacyDomain::class => new LegacyDomain(),
            JsonResponder::class => new JsonResponder(),
            LegacyInput::class => new LegacyInput(new InputCollection()),
            default => new $class(),
        };

        $relay = new Relay([
            new DispatcherMiddleware($dispatcher),
            new ActionMiddleware($resolver, new ResponseFactory()),
        ]);

        return $relay->handle($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(ResponseInterface $response): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $response->getBody(), true);

        return $decoded;
    }

    public function testTypedDtoEndpointExecutesAndReturns200(): void
    {
        $request = (new ServerRequest(uri: '/greet', method: 'GET'))
            ->withQueryParams(['name' => 'Ada', 'times' => '3']);

        $response = $this->dispatch($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['hello' => 'Ada', 'times' => 3], $this->json($response));
    }

    public function testMissingRequiredFieldReturns422(): void
    {
        $response = $this->dispatch(new ServerRequest(uri: '/greet', method: 'GET'));

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('name', $this->json($response)['errors']);
    }

    public function testLegacyInputInterfaceEndpointStillWorks(): void
    {
        $response = $this->dispatch(new ServerRequest(uri: '/legacy', method: 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['echo' => 'legacy'], $this->json($response));
    }
}

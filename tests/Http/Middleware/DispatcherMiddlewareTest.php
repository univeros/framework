<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Middleware;

use Altair\Http\Base\Action;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Exception\HttpMethodNotAllowedException;
use Altair\Http\Exception\HttpNotFoundException;
use Altair\Http\Middleware\DispatcherMiddleware;
use FastRoute\DataGenerator\GroupCountBased as DataGenerator;
use FastRoute\Dispatcher\GroupCountBased;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DispatcherMiddlewareTest extends AbstractMiddlewareTest
{
    public function testActionAndRouteArgsAreSetAsRequestAttributes(): void
    {
        $action = $this->createMock(Action::class);
        $collector = new RouteCollector(new Std(), new DataGenerator());
        $collector->addRoute('GET', '/users/{id:\d+}', $action);

        $middleware = new DispatcherMiddleware(new GroupCountBased($collector->getData()));
        $request = (new ServerRequest())->withMethod('GET')->withUri(new \Laminas\Diactoros\Uri('/users/42'));

        $captured = null;
        $this->dispatch([$middleware, $this->captureRequest($captured)], $request);

        $this->assertSame($action, $captured->getAttribute(MiddlewareInterface::ATTRIBUTE_ACTION));
        $this->assertSame('42', $captured->getAttribute('id'));
    }

    public function testThrowsNotFoundWhenNoRouteMatches(): void
    {
        $collector = new RouteCollector(new Std(), new DataGenerator());
        $middleware = new DispatcherMiddleware(new GroupCountBased($collector->getData()));
        $request = (new ServerRequest())->withMethod('GET')->withUri(new \Laminas\Diactoros\Uri('/missing'));

        $this->expectException(HttpNotFoundException::class);

        $this->dispatch([$middleware, $this->okHandler()], $request);
    }

    public function testThrowsMethodNotAllowedWhenMethodMismatches(): void
    {
        $collector = new RouteCollector(new Std(), new DataGenerator());
        $collector->addRoute('POST', '/things', $this->createMock(Action::class));

        $middleware = new DispatcherMiddleware(new GroupCountBased($collector->getData()));
        $request = (new ServerRequest())->withMethod('GET')->withUri(new \Laminas\Diactoros\Uri('/things'));

        $this->expectException(HttpMethodNotAllowedException::class);

        $this->dispatch([$middleware, $this->okHandler()], $request);
    }

    private function captureRequest(?ServerRequestInterface &$capture): PsrMiddlewareInterface
    {
        return new class ($capture) implements PsrMiddlewareInterface {
            public function __construct(private ?ServerRequestInterface &$capture)
            {
            }

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->capture = $request;

                return new Response('php://temp', 200);
            }
        };
    }

    private function okHandler(): PsrMiddlewareInterface
    {
        return new class () implements PsrMiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new Response('php://temp', 200);
            }
        };
    }
}

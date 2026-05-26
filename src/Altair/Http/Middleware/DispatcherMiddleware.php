<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Middleware;

use Altair\Http\Base\Action;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Exception\HttpMethodNotAllowedException;
use Altair\Http\Exception\HttpNotFoundException;
use FastRoute\Dispatcher;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DispatcherMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        [$action, $args] = $this->dispatch(
            $this->dispatcher,
            $request->getMethod(),
            $request->getUri()->getPath(),
        );

        $request = $request->withAttribute(MiddlewareInterface::ATTRIBUTE_ACTION, $action);
        foreach ($args as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        return $handler->handle($request);
    }

    /**
     *
     * @throws HttpMethodNotAllowedException
     * @throws HttpNotFoundException
     * @return array{0: Action, 1: array<string, mixed>}
     */
    private function dispatch(Dispatcher $dispatcher, string $method, string $path): array
    {
        $route = $dispatcher->dispatch($method, $path);
        $status = array_shift($route);

        return match ($status) {
            Dispatcher::FOUND => $route,
            Dispatcher::METHOD_NOT_ALLOWED => throw new HttpMethodNotAllowedException(
                array_shift($route),
                \sprintf("Cannot access resource '%s' using method '%s'", $path, $method),
                405,
            ),
            default => throw new HttpNotFoundException($path),
        };
    }
}

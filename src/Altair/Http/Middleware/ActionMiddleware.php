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
use Altair\Http\Contracts\DomainInterface;
use Altair\Http\Contracts\InputInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Contracts\PayloadInterface;
use Altair\Http\Contracts\ResponderInterface;
use Altair\Http\Traits\ResolverAwareTrait;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Terminal middleware: marshals the request-bound Action through its domain/input/responder collaborators
 * and returns the produced response. Any decorator middleware (CORS, cache headers, etc.) must run before
 * this middleware in the pipeline so they wrap the response that bubbles back up.
 */
class ActionMiddleware implements MiddlewareInterface
{
    use ResolverAwareTrait;

    public function __construct(
        callable $resolver,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
        $this->resolver = $resolver;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $action = $request->getAttribute(MiddlewareInterface::ATTRIBUTE_ACTION);
        $request = $request->withoutAttribute(MiddlewareInterface::ATTRIBUTE_ACTION);

        if (!$action instanceof Action) {
            return $handler->handle($request);
        }

        return $this->handle($action, $request);
    }

    private function handle(Action $action, ServerRequestInterface $request): ResponseInterface
    {
        /** @var DomainInterface $domain */
        $domain = $this->resolve($action->getDomainClassName());
        /** @var InputInterface $input */
        $input = $this->resolve($action->getInputClassName());
        /** @var ResponderInterface $responder */
        $responder = $this->resolve($action->getResponderClassName());

        $payload = $this->payload($domain, $input, $request);

        return $responder($request, $this->responseFactory->createResponse(), $payload);
    }

    private function payload(
        DomainInterface $domain,
        InputInterface $input,
        ServerRequestInterface $request,
    ): PayloadInterface {
        return $domain($input($request));
    }
}

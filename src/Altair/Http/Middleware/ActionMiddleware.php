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
use Altair\Http\Base\Payload;
use Altair\Http\Contracts\InputInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Contracts\PayloadInterface;
use Altair\Http\Contracts\ResponderInterface;
use Altair\Http\Exception\InputValidationException;
use Altair\Http\Exception\InvalidArgumentException;
use Altair\Http\Input\DtoInputHydrator;
use Altair\Http\Traits\ResolverAwareTrait;
use Override;
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
        private readonly DtoInputHydrator $hydrator = new DtoInputHydrator(),
    ) {
        $this->resolver = $resolver;
    }

    #[Override]
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
        /** @var ResponderInterface $responder */
        $responder = $this->resolve($action->getResponderClassName());

        try {
            $payload = $this->resolvePayload($action, $request);
        } catch (InputValidationException $inputValidationException) {
            $payload = (new Payload())
                ->withStatus(422)
                ->withOutput(['errors' => $inputValidationException->errors]);
        }

        return $responder($request, $this->responseFactory->createResponse(), $payload);
    }

    /**
     * Two input shapes are supported. The legacy shape implements
     * {@see InputInterface} (a request bag) and pairs with a
     * {@see DomainInterface}. The spec-scaffolded shape is a typed DTO that is
     * hydrated from the request and handed to an invokable domain as
     * `$domain($dto, $payload)`.
     */
    private function resolvePayload(Action $action, ServerRequestInterface $request): PayloadInterface
    {
        $domain = $this->resolve($action->getDomainClassName());
        if (!\is_callable($domain)) {
            throw new InvalidArgumentException(\sprintf(
                '%s must be invokable to act as a domain.',
                $action->getDomainClassName(),
            ));
        }

        $inputClass = $action->getInputClassName();

        if (is_a($inputClass, InputInterface::class, true)) {
            // Legacy path: request-bag input handed to the domain as an InputCollection.
            /** @var InputInterface $input */
            $input = $this->resolve($inputClass);
            $payload = $domain($input($request));
        } else {
            // Typed-DTO path: hydrate the DTO from the request and hand it to the
            // domain alongside a fresh Payload to build on.
            if (!class_exists($inputClass)) {
                throw new InvalidArgumentException(\sprintf("Input class '%s' does not exist.", $inputClass));
            }

            $payload = $domain($this->hydrator->hydrate($inputClass, $request), new Payload());
        }

        if (!$payload instanceof PayloadInterface) {
            throw new InvalidArgumentException(\sprintf(
                '%s::__invoke() must return %s.',
                $action->getDomainClassName(),
                PayloadInterface::class,
            ));
        }

        return $payload;
    }
}

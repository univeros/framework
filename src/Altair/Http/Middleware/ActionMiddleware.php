<?php
namespace Altair\Http\Middleware;

use Altair\Http\Base\Action;
use Altair\Http\Contracts\DomainInterface;
use Altair\Http\Contracts\InputInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Contracts\PayloadInterface;
use Altair\Http\Contracts\ResponderInterface;
use Altair\Http\Traits\ResolverAwareTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Relay\ResolverInterface;

class ActionMiddleware implements MiddlewareInterface
{
    use ResolverAwareTrait;

    /**
     * @param ResolverInterface $resolver
     */
    public function __construct(ResolverInterface $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * @inheritdoc
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $next
    ) {
        $action = $request->getAttribute(MiddlewareInterface::ATTRIBUTE_ACTION);
        $request = $request->withoutAttribute(MiddlewareInterface::ATTRIBUTE_ACTION);
        $response = $this->handle($action, $request, $response);
        return $next($request, $response);
    }
    /**
     * Use the action collaborators to get a response.
     *
     * @param Action $action
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    private function handle(
        Action $action,
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        /** @var DomainInterface $domain */
        $domain = $this->resolve($action->getDomain());
        /** @var InputInterface $input */
        $input = $this->resolve($action->getInput());
        /** @var ResponderInterface $responder */
        $responder = $this->resolve($action->getResponder());
        $payload = $this->payload($domain, $input, $request);
        $response = $this->response($responder, $request, $response, $payload);

        return $response;
    }
    /**
     * Execute the domain to get a payload using input from the request.
     *
     * @param DomainInterface $domain
     * @param InputInterface $input
     * @param ServerRequestInterface $request
     *
     * @return PayloadInterface
     */
    private function payload(
        DomainInterface $domain,
        InputInterface $input,
        ServerRequestInterface $request
    ) {
        return $domain($input($request));
    }
    /**
     * Execute the responder to marshall the payload into the response.
     *
     * @param ResponderInterface $responder
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param PayloadInterface $payload
     *
     * @return ResponseInterface
     */
    private function response(
        ResponderInterface $responder,
        ServerRequestInterface $request,
        ResponseInterface $response,
        PayloadInterface $payload
    ) {
        return $responder($request, $response, $payload);
    }
}

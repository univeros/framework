<?php
namespace Altair\Http\Responder;

use Altair\Http\Contracts\PayloadInterface;
use Altair\Http\Contracts\ResponderInterface;
use Altair\Http\Exception\InvalidResponderException;
use Altair\Http\Traits\ResolverAwareTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Relay\ResolverInterface;

class CompoundResponder implements ResponderInterface
{
    use ResolverAwareTrait;

    /**
     * @var array
     */
    protected $responders;

    /**
     * @param ResolverInterface $resolver
     * @param array $responders
     */
    public function __construct(
        ResolverInterface $resolver,
        array $responders = [
            FormattedResponder::class,
            RedirectResponder::class,
            StatusResponder::class,
        ]
    ) {
        $this->resolver = $resolver;
        $this->responders = $this->filterResponders($responders);
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param PayloadInterface $payload
     *
     * @return ResponseInterface
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        PayloadInterface $payload
    ): ResponseInterface {
        foreach ($this->responders as $responder) {
            /** @var callable $responder */
            $responder = $this->resolve($responder);
            $response = $responder($request, $response, $payload);
        }

        return $response;
    }

    /**
     * @param array $responders
     *
     * @return array
     */
    protected function filterResponders(array $responders): array
    {
        $filtered = [];
        foreach ($responders as $responder) {
            if (!is_subclass_of($responder, ResponderInterface::class)) {
                throw new InvalidResponderException("Invalid responder '{$responder}'");
            }
            $filtered[] = $responder;
        }

        return $filtered;
    }
}

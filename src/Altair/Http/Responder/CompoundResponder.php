<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Responder;

use Altair\Http\Contracts\PayloadInterface;
use Altair\Http\Contracts\ResponderInterface;
use Altair\Http\Exception\InvalidResponderException;
use Altair\Http\Traits\ResolverAwareTrait;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CompoundResponder implements ResponderInterface
{
    use ResolverAwareTrait;

    protected array $responders;

    /**
     * @param callable(string): object $resolver
     *
     * @throws InvalidResponderException
     */
    public function __construct(
        callable $resolver,
        array $responders = [
            FormattedResponder::class,
            RedirectResponder::class,
            StatusResponder::class,
        ]
    ) {
        $this->resolver = $resolver;
        $this->responders = $this->filterResponders($responders);
    }

    #[Override]
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
     *
     * @throws InvalidResponderException
     *
     */
    protected function filterResponders(array $responders): array
    {
        $filtered = [];
        foreach ($responders as $responder) {
            if (!is_subclass_of($responder, ResponderInterface::class)) {
                throw new InvalidResponderException(\sprintf("Invalid responder '%s'", $responder));
            }

            $filtered[] = $responder;
        }

        return $filtered;
    }
}

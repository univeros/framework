<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Middleware;

use Altair\Http\Contracts\FormatNegotiatorInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class FormatNegotiatorMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly FormatNegotiatorInterface $negotiator,
    ) {
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $format = ($this->negotiator->getFromServerRequestUriPath($request)
            ?: $this->negotiator->getFromServerRequestHeaderLine($request)) ?: FormatNegotiatorInterface::DEFAULT_FORMAT;
        $contentType = $this->negotiator->getContentTypeByFormat($format);

        $response = $handler->handle(
            $request->withAttribute(MiddlewareInterface::ATTRIBUTE_FORMAT, $format),
        );

        return $response->hasHeader('Content-Type')
            ? $response
            : $response->withHeader('Content-Type', $contentType);
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Middleware;

use Altair\Http\Contracts\MiddlewareInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Parses the request body when its Content-Type matches one of the configured media types,
 * then delegates to the handler with the parsed body set on the request.
 */
abstract class AbstractContentHandlerMiddleware implements MiddlewareInterface
{
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->matchesContentType($request)) {
            return $handler->handle($request);
        }

        $body = (string) $request->getBody();
        if ($body === '') {
            return $handler->handle($request);
        }

        return $handler->handle($request->withParsedBody($this->parse($body)));
    }
    /**
     * @return list<string>
     */
    abstract protected function contentTypes(): array;

    /**
     * @return array<string, mixed>|object|null
     */
    abstract protected function parse(string $body): array|object|null;

    private function matchesContentType(ServerRequestInterface $request): bool
    {
        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        if ($contentType === '') {
            return false;
        }

        foreach ($this->contentTypes() as $type) {
            if (str_starts_with($contentType, strtolower($type))) {
                return true;
            }
        }

        return false;
    }
}

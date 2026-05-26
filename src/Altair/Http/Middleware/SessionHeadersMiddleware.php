<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Middleware;

use Altair\Cookie\CookieManager;
use Altair\Cookie\SetCookie;
use Altair\Http\Contracts\CacheLimiterInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SessionHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly CookieManager $cookieManager,
        private readonly CacheLimiterInterface $cacheLimiter,
    ) {
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $prevName = session_name();
        $prevId = $this->cookieManager->getFromRequest($request, $prevName);
        session_id($prevId);

        $response = $handler->handle($request);

        $nextId = session_id();
        $response = $this->cookieManager->setOnResponse(
            $response,
            $this->createNewSessionCookie($nextId),
        );

        return $nextId !== ''
            ? $this->cacheLimiter->apply($response)
            : $response;
    }

    private function createNewSessionCookie(string $sessionId): SetCookie
    {
        $params = session_get_cookie_params();

        return (new SetCookie(session_name(), $sessionId))
            ->withDomain($params['domain'] ?? null)
            ->withExpires($params['lifetime'] ?? null)
            ->withSecure($params['path'] ?? null)
            ->withHttpOnly($params['httponly'] ?? null);
    }
}

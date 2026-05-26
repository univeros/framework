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
        $prevCookie = $this->cookieManager->getFromRequest($request, $prevName);
        $prevId = $prevCookie?->getValue();
        if ($prevId !== null) {
            session_id($prevId);
        }

        $response = $handler->handle($request);

        $nextId = session_id();
        if ($nextId !== $prevId) {
            $response = $this->cookieManager->setOnResponse(
                $response,
                $this->createNewSessionCookie($nextId !== false ? $nextId : ''),
            );
        }

        return $nextId !== '' && $nextId !== false
            ? $this->cacheLimiter->apply($response)
            : $response;
    }

    private function createNewSessionCookie(string $sessionId): SetCookie
    {
        $params = session_get_cookie_params();

        return (new SetCookie((string) session_name(), $sessionId))
            ->withDomain($params['domain'] ?? null)
            ->withPath($params['path'] ?? null)
            ->withExpires($params['lifetime'] ?? null)
            ->withSecure(!empty($params['secure']))
            ->withHttpOnly(!empty($params['httponly']));
    }
}

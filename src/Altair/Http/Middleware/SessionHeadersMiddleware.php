<?php
namespace Altair\Http\Middleware;

use Altair\Cookie\CookieManager;
use Altair\Cookie\SetCookie;
use Altair\Http\Contracts\CacheLimiterInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SessionHeadersMiddleware implements MiddlewareInterface
{
    /**
     * @var CookieManager
     */
    protected $cookieManager;
    /**
     *
     * The cache limiter type, if any.
     *
     * @var string
     *
     * @see session_cache_limiter()
     *
     */
    protected $cacheLimiter;

    /**
     * SessionHeadersMiddleware constructor.
     *
     * @param CookieManager $cookieManager
     * @param CacheLimiterInterface $cacheLimiter
     */
    public function __construct(CookieManager $cookieManager, CacheLimiterInterface $cacheLimiter)
    {
        $this->cookieManager = $cookieManager;
        $this->cacheLimiter = $cacheLimiter;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable|null $next
     *
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next = null)
    {
        $prevName = session_name();
        $prevId = $this->cookieManager->getFromRequest($request, $prevName);
        if ($prevId !== null) {
            session_id($prevId);
        }

        $response = $next($request, $response);

        // is the session id still the same?
        $nextId = session_id();

        if ($nextId !== $prevId) {
            $cookie = $this->createNewSessionCookie($nextId);
            $response = $this->cookieManager->setOnResponse($response, $cookie);
        }

        return $nextId
            ? $this->cacheLimiter->apply($response)
            : $response;
    }

    /**
     * @param string $sessionId
     *
     * @return SetCookie
     */
    protected function createNewSessionCookie(string $sessionId): SetCookie
    {
        $params = session_get_cookie_params();

        return (new SetCookie(session_name(), $sessionId))
            ->withDomain(($params['domain']?? null))
            ->withExpires(($params['lifetime']?? null))
            ->withSecure(($params['path']?? null))
            ->withHttpOnly(($params['httponly'] ?? null));
    }
}

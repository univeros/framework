<?php
namespace Altair\Http\Middleware;

use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Micheh\Cache\CacheUtil;
use Micheh\Cache\Header\CacheControl;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;

class CacheMiddleware implements MiddlewareInterface
{
    /**
     * @var CacheItemPoolInterface The cache implementation used
     */
    protected $cache;
    /**
     * @var CacheUtil
     */
    protected $cacheUtil;
    /**
     * @var CacheControl
     */
    protected $cacheControl;

    /**
     * CacheMiddleware constructor.
     *
     * @param CacheItemPoolInterface $cache
     * @param CacheUtil $cacheUtil
     * @param CacheControl $cacheControl
     */
    public function __construct(CacheItemPoolInterface $cache, CacheUtil $cacheUtil, CacheControl $cacheControl)
    {
        $this->cache = $cache;
        $this->cacheUtil = $cacheUtil;
        $this->cacheControl = $cacheControl;
    }

    /**
     * @inheritdoc
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $key = $this->getCacheKey($request);
        $item = $this->cache->getItem($key);
        if ($item->isHit()) {
            /** @var Response $response */
            $cachedResponse = $response->withStatus(HttpStatusCodeInterface::HTTP_NOT_MODIFIED);
            foreach ($item->get() as $name => $header) {
                $cachedResponse = $cachedResponse->withHeader($name, $header);
            }
            if ($this->cacheUtil->isNotModified($request, $cachedResponse)) {
                return $cachedResponse;
            }
            $this->cache->deleteItem($key);
        }
        /** @var ResponseInterface $response */
        $response = $next($request, $response);
        $response = $this->ensureCacheControlHeader($response);
        $response = $this->ensureLastModified($response);
        $response = $this->checkETag($request, $response);
        $this->saveToCache($item, $response);

        return $response;
    }

    /**
     * @param ResponseInterface $response
     *
     * @return ResponseInterface|MessageInterface
     */
    protected function ensureCacheControlHeader(ResponseInterface $response)
    {
        return !$response->hasHeader('Cache-Control')
            ? $this->cacheUtil->withCacheControl($response, $this->cacheControl)
            : $response;
    }

    /**
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    protected function ensureLastModified(ResponseInterface $response): ResponseInterface
    {
        return !$response->hasHeader('Last-Modified')
            ? $this->cacheUtil->withLastModified($response, time())
            : $response;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    protected function checkETag(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $etag = $response->getHeader('ETag');
        $etag = reset($etag);
        if ($etag) {
            $ifNoneMatch = $request->getHeaderLine('If-None-Match');
            if ($ifNoneMatch) {
                $etagList = preg_split('@\s*,\s*@', $ifNoneMatch);
                if (in_array($etag, $etagList) || in_array('*', $etagList)) {
                    return $response->withStatus(304);
                }
            }
        }
        return $response;
    }

    /**
     * @param CacheItemInterface $item
     * @param ResponseInterface $response
     *
     * @return bool
     */
    protected function saveToCache(CacheItemInterface $item, ResponseInterface $response): bool
    {
        if ($this->cacheUtil->isCacheable($response)) {
            $item->set($response->getHeaders());
            $item->expiresAfter($this->cacheUtil->getLifetime($response));

            return $this->cache->save($item);
        }

        return false;
    }

    /**
     * Returns the id used to cache a request.
     *
     * @param RequestInterface $request
     *
     * @return string
     */
    protected function getCacheKey(RequestInterface $request): string
    {
        return $request->getMethod() . sha1((string)$request->getUri());
    }
}

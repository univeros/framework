<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Middleware;

use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Micheh\Cache\CacheUtil;
use Micheh\Cache\Header\CacheControl;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CacheMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly CacheUtil $cacheUtil,
        private readonly CacheControl $cacheControl,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $key = $this->getCacheKey($request);
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            $cached = $this->responseFactory->createResponse(HttpStatusCodeInterface::HTTP_NOT_MODIFIED);
            foreach ($item->get() as $name => $header) {
                $cached = $cached->withHeader($name, $header);
            }
            if ($this->cacheUtil->isNotModified($request, $cached)) {
                return $cached;
            }
            $this->cache->deleteItem($key);
        }

        $response = $handler->handle($request);
        $response = $this->ensureCacheControlHeader($response);
        $response = $this->ensureLastModified($response);
        $response = $this->checkETag($request, $response);
        $this->saveToCache($item, $response);

        return $response;
    }

    private function ensureCacheControlHeader(ResponseInterface $response): ResponseInterface
    {
        return $response->hasHeader('Cache-Control')
            ? $response
            : $this->cacheUtil->withCacheControl($response, $this->cacheControl);
    }

    private function ensureLastModified(ResponseInterface $response): ResponseInterface
    {
        return $response->hasHeader('Last-Modified')
            ? $response
            : $this->cacheUtil->withLastModified($response, time());
    }

    private function checkETag(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $etag = $response->getHeader('ETag');
        $etag = reset($etag);
        if ($etag === false) {
            return $response;
        }

        $ifNoneMatch = $request->getHeaderLine('If-None-Match');
        if ($ifNoneMatch === '') {
            return $response;
        }

        $etagList = preg_split('@\s*,\s*@', $ifNoneMatch) ?: [];

        return in_array($etag, $etagList, true) || in_array('*', $etagList, true)
            ? $response->withStatus(HttpStatusCodeInterface::HTTP_NOT_MODIFIED)
            : $response;
    }

    private function saveToCache(CacheItemInterface $item, ResponseInterface $response): bool
    {
        if (!$this->cacheUtil->isCacheable($response)) {
            return false;
        }

        $item->set($response->getHeaders());
        $item->expiresAfter($this->cacheUtil->getLifetime($response));

        return $this->cache->save($item);
    }

    private function getCacheKey(RequestInterface $request): string
    {
        return $request->getMethod() . sha1((string) $request->getUri());
    }
}

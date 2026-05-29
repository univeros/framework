<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Middleware\RateLimit;

use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Middleware\RateLimit\Contracts\KeyResolverInterface;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * A fixed-window PSR-15 rate limiter, backed by a PSR-16 cache pool.
 *
 * Counters live in the cache under `{prefix}:{key}:{bucket}`, where
 * `bucket = floor(time() / windowSeconds)` — each window owns its own
 * counter, so a new window starts fresh and the previous one can be evicted
 * by its TTL. Under-limit requests pass through with `X-RateLimit-Limit /
 * Remaining / Reset` informational headers; at-limit requests get a `429`
 * with `Retry-After` and the same `X-RateLimit-*` set.
 *
 * Trade-offs to know:
 *
 * - **Fixed-window** is the simplest accurate counter; under burst traffic at
 *   the window boundary a client can fire `2 × limit` in a few seconds (the
 *   tail of one window + the head of the next). For most use cases this is
 *   fine; if it isn't, layer a token-bucket on top — same backend, different
 *   accounting.
 * - PSR-16 has no atomic `increment`, so the read-modify-write is racey under
 *   concurrent load on a shared backend. The race overcounts at most by N-1
 *   in-flight requests; given the limiter's job (defence-in-depth, not
 *   exactness), the simpler API is the right v1 trade.
 * - This is a complement to edge / reverse-proxy rate limiting, not a
 *   replacement. The proxy stops floods before they touch PHP; this catches
 *   the surviving abuse with per-key precision.
 */
final readonly class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CacheInterface $cache,
        private RateLimit $policy,
        private ResponseFactoryInterface $responseFactory,
        private KeyResolverInterface $keyResolver = new IpKeyResolver(),
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $now = time();
        $bucket = intdiv($now, $this->policy->windowSeconds);
        $resetAt = ($bucket + 1) * $this->policy->windowSeconds;
        $key = $this->cacheKey($request, $bucket);

        $count = (int) ($this->cache->get($key) ?? 0);

        if ($count >= $this->policy->limit) {
            return $this->tooManyRequests($resetAt - $now, $resetAt);
        }

        $this->cache->set($key, $count + 1, $this->policy->windowSeconds);

        return $this->withRateLimitHeaders(
            $handler->handle($request),
            remaining: max(0, $this->policy->limit - ($count + 1)),
            resetAt: $resetAt,
        );
    }

    private function cacheKey(ServerRequestInterface $request, int $bucket): string
    {
        // PSR-16 reserves `{ } ( ) / \ @ :` in keys, so we hash the user-provided
        // segment instead of trying to escape it.
        $subject = hash('xxh128', $this->keyResolver->resolve($request));

        return \sprintf('%s.%s.%d', $this->policy->keyPrefix, $subject, $bucket);
    }

    private function tooManyRequests(int $retryAfter, int $resetAt): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(
            HttpStatusCodeInterface::HTTP_TOO_MANY_REQUESTS,
            'Too Many Requests',
        );

        return $response
            ->withHeader('Retry-After', (string) $retryAfter)
            ->withHeader('X-RateLimit-Limit', (string) $this->policy->limit)
            ->withHeader('X-RateLimit-Remaining', '0')
            ->withHeader('X-RateLimit-Reset', (string) $resetAt);
    }

    private function withRateLimitHeaders(ResponseInterface $response, int $remaining, int $resetAt): ResponseInterface
    {
        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->policy->limit)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining)
            ->withHeader('X-RateLimit-Reset', (string) $resetAt);
    }
}

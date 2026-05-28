<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Support;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Minimal HTTP cache header helpers — replaces the abandoned micheh/psr7-cache
 * package. Covers Cache-Control, Last-Modified, ETag/If-None-Match, and
 * If-Modified-Since validators (RFC 7234).
 */
final class HttpCache
{
    public function withCacheControl(ResponseInterface $response, string $cacheControl): ResponseInterface
    {
        return $response->withHeader('Cache-Control', $cacheControl);
    }

    public function withLastModified(ResponseInterface $response, int $timestamp): ResponseInterface
    {
        return $response->withHeader('Last-Modified', gmdate('D, d M Y H:i:s', $timestamp) . ' GMT');
    }

    /**
     * Returns true when the request's validators (If-None-Match / If-Modified-Since)
     * indicate the cached response is still fresh (= 304 Not Modified).
     */
    public function isNotModified(RequestInterface $request, ResponseInterface $response): bool
    {
        $ifNoneMatch = $request->getHeaderLine('If-None-Match');
        if ($ifNoneMatch !== '') {
            return $this->etagMatches($ifNoneMatch, $response->getHeaderLine('ETag'));
        }

        $ifModifiedSince = $request->getHeaderLine('If-Modified-Since');
        $lastModified = $response->getHeaderLine('Last-Modified');
        if ($ifModifiedSince === '' || $lastModified === '') {
            return false;
        }

        return strtotime($ifModifiedSince) >= strtotime($lastModified);
    }

    /**
     * A response is cacheable when its Cache-Control directives don't forbid storage
     * and it has a lifetime > 0 (or no explicit max-age / Expires but is otherwise
     * safe to store). Conservative: returns false unless storage is clearly allowed.
     */
    public function isCacheable(ResponseInterface $response): bool
    {
        $directives = $this->parseCacheControl($response);
        if (isset($directives['no-store']) || isset($directives['private'])) {
            return false;
        }

        return $this->getLifetime($response) > 0;
    }

    /**
     * Returns the response's effective cache lifetime in seconds, or 0 when
     * unknown. Prefers `s-maxage`, then `max-age`, then `Expires`.
     */
    public function getLifetime(ResponseInterface $response): int
    {
        $directives = $this->parseCacheControl($response);
        if (isset($directives['s-maxage'])) {
            return max(0, (int) $directives['s-maxage']);
        }

        if (isset($directives['max-age'])) {
            return max(0, (int) $directives['max-age']);
        }

        $expires = $response->getHeaderLine('Expires');
        if ($expires === '') {
            return 0;
        }

        $expiresAt = strtotime($expires);

        return $expiresAt === false ? 0 : max(0, $expiresAt - time());
    }

    /**
     * @return array<string, string|true>
     */
    private function parseCacheControl(ResponseInterface $response): array
    {
        $header = $response->getHeaderLine('Cache-Control');
        if ($header === '') {
            return [];
        }

        $directives = [];
        foreach (preg_split('/\s*,\s*/', $header) ?: [] as $directive) {
            if (str_contains($directive, '=')) {
                [$key, $value] = explode('=', $directive, 2);
                $directives[strtolower(trim($key))] = trim($value, " \t\"");
            } else {
                $directives[strtolower(trim($directive))] = true;
            }
        }

        return $directives;
    }

    private function etagMatches(string $ifNoneMatch, string $responseEtag): bool
    {
        $tags = array_map(trim(...), explode(',', $ifNoneMatch));

        return \in_array('*', $tags, true) || ($responseEtag !== '' && \in_array($responseEtag, $tags, true));
    }
}

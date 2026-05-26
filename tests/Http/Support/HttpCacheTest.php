<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Support;

use Altair\Http\Support\HttpCache;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;

class HttpCacheTest extends TestCase
{
    private HttpCache $cache;

    #[\Override]
    protected function setUp(): void
    {
        $this->cache = new HttpCache();
    }

    public function testWithCacheControlSetsHeader(): void
    {
        $response = $this->cache->withCacheControl(new Response(), 'public, max-age=60');

        $this->assertSame('public, max-age=60', $response->getHeaderLine('Cache-Control'));
    }

    public function testWithLastModifiedSetsRfc7231FormattedHeader(): void
    {
        $response = $this->cache->withLastModified(new Response(), 0);

        $this->assertSame('Thu, 01 Jan 1970 00:00:00 GMT', $response->getHeaderLine('Last-Modified'));
    }

    public function testIsNotModifiedReturnsTrueWhenIfNoneMatchEtagMatches(): void
    {
        $request = (new ServerRequest())->withHeader('If-None-Match', '"abc123"');
        $response = (new Response())->withHeader('ETag', '"abc123"');

        $this->assertTrue($this->cache->isNotModified($request, $response));
    }

    public function testIsNotModifiedReturnsTrueForWildcardIfNoneMatch(): void
    {
        $request = (new ServerRequest())->withHeader('If-None-Match', '*');
        $response = (new Response())->withHeader('ETag', '"anything"');

        $this->assertTrue($this->cache->isNotModified($request, $response));
    }

    public function testIsNotModifiedReturnsFalseWhenEtagDiffers(): void
    {
        $request = (new ServerRequest())->withHeader('If-None-Match', '"old"');
        $response = (new Response())->withHeader('ETag', '"new"');

        $this->assertFalse($this->cache->isNotModified($request, $response));
    }

    public function testIsNotModifiedReturnsTrueWhenIfModifiedSinceIsAtOrAfterLastModified(): void
    {
        $request = (new ServerRequest())->withHeader('If-Modified-Since', 'Wed, 21 Oct 2015 07:28:00 GMT');
        $response = (new Response())->withHeader('Last-Modified', 'Wed, 21 Oct 2015 07:28:00 GMT');

        $this->assertTrue($this->cache->isNotModified($request, $response));
    }

    public function testIsNotModifiedReturnsFalseWhenIfModifiedSinceIsBeforeLastModified(): void
    {
        $request = (new ServerRequest())->withHeader('If-Modified-Since', 'Tue, 20 Oct 2015 00:00:00 GMT');
        $response = (new Response())->withHeader('Last-Modified', 'Wed, 21 Oct 2015 07:28:00 GMT');

        $this->assertFalse($this->cache->isNotModified($request, $response));
    }

    public function testIsNotModifiedReturnsFalseWhenNeitherValidatorIsPresent(): void
    {
        $this->assertFalse($this->cache->isNotModified(new ServerRequest(), new Response()));
    }

    public function testIsCacheableReturnsTrueWithMaxAge(): void
    {
        $response = (new Response())->withHeader('Cache-Control', 'public, max-age=60');

        $this->assertTrue($this->cache->isCacheable($response));
    }

    public function testIsCacheableReturnsFalseForNoStore(): void
    {
        $response = (new Response())->withHeader('Cache-Control', 'no-store, max-age=60');

        $this->assertFalse($this->cache->isCacheable($response));
    }

    public function testIsCacheableReturnsFalseForPrivate(): void
    {
        $response = (new Response())->withHeader('Cache-Control', 'private, max-age=60');

        $this->assertFalse($this->cache->isCacheable($response));
    }

    public function testIsCacheableReturnsFalseWhenLifetimeIsZero(): void
    {
        $this->assertFalse($this->cache->isCacheable(new Response()));
    }

    public function testGetLifetimeFromSMaxAge(): void
    {
        $response = (new Response())->withHeader('Cache-Control', 's-maxage=120, max-age=60');

        $this->assertSame(120, $this->cache->getLifetime($response));
    }

    public function testGetLifetimeFromMaxAge(): void
    {
        $response = (new Response())->withHeader('Cache-Control', 'public, max-age=60');

        $this->assertSame(60, $this->cache->getLifetime($response));
    }

    public function testGetLifetimeFromExpiresHeader(): void
    {
        $future = gmdate('D, d M Y H:i:s', time() + 100) . ' GMT';
        $response = (new Response())->withHeader('Expires', $future);

        $this->assertGreaterThan(90, $this->cache->getLifetime($response));
        $this->assertLessThanOrEqual(100, $this->cache->getLifetime($response));
    }

    public function testGetLifetimeReturnsZeroForExpiredOrAbsent(): void
    {
        $this->assertSame(0, $this->cache->getLifetime(new Response()));

        $past = gmdate('D, d M Y H:i:s', time() - 100) . ' GMT';
        $expired = (new Response())->withHeader('Expires', $past);

        $this->assertSame(0, $this->cache->getLifetime($expired));
    }
}

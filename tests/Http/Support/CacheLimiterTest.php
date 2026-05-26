<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Support;

use Altair\Http\Contracts\CacheLimiterInterface;
use Altair\Http\Support\NoCacheLimiter;
use Altair\Http\Support\PrivateCacheLimiter;
use Altair\Http\Support\PrivateNoExpireCacheLimiter;
use Altair\Http\Support\PublicCacheLimiter;
use Laminas\Diactoros\Response;
use PHPUnit\Framework\TestCase;

class CacheLimiterTest extends TestCase
{
    public function testNoCacheSetsExpiredHeadersAndNoStoreDirective(): void
    {
        $response = (new NoCacheLimiter())->apply(new Response());

        $this->assertSame(CacheLimiterInterface::EXPIRED, $response->getHeaderLine('Expires'));
        $this->assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));
        $this->assertSame('no-cache', $response->getHeaderLine('Pragma'));
    }

    public function testPublicCacheSetsPublicMaxAge(): void
    {
        $response = (new PublicCacheLimiter())->apply(new Response());

        $this->assertStringContainsString('public, max-age=', $response->getHeaderLine('Cache-Control'));
        $this->assertNotSame('', $response->getHeaderLine('Expires'));
        $this->assertNotSame('', $response->getHeaderLine('Last-Modified'));
    }

    public function testPrivateCacheSetsPrivateMaxAge(): void
    {
        $response = (new PrivateCacheLimiter())->apply(new Response());

        $this->assertStringContainsString('private', $response->getHeaderLine('Cache-Control'));
    }

    public function testPrivateNoExpireCacheDoesNotEmitExpiresHeader(): void
    {
        $response = (new PrivateNoExpireCacheLimiter())->apply(new Response());

        $this->assertStringContainsString('private', $response->getHeaderLine('Cache-Control'));
        $this->assertSame('', $response->getHeaderLine('Expires'));
    }
}

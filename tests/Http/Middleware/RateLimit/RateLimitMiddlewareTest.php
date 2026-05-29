<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Http\Middleware\RateLimit;

use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Middleware\RateLimit\Contracts\KeyResolverInterface;
use Altair\Http\Middleware\RateLimit\IpKeyResolver;
use Altair\Http\Middleware\RateLimit\RateLimit;
use Altair\Http\Middleware\RateLimit\RateLimitMiddleware;
use InvalidArgumentException;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(RateLimitMiddleware::class)]
#[CoversClass(RateLimit::class)]
#[CoversClass(IpKeyResolver::class)]
final class RateLimitMiddlewareTest extends TestCase
{
    public function testRequestsUnderTheLimitPassThroughWithInformationalHeaders(): void
    {
        $middleware = new RateLimitMiddleware(
            new InMemoryCache(),
            new RateLimit(limit: 3, windowSeconds: 60),
            new ResponseFactory(),
        );

        for ($i = 0; $i < 3; ++$i) {
            $response = $middleware->process(
                $this->request('1.2.3.4'),
                $this->okHandler(),
            );
            self::assertSame(200, $response->getStatusCode(), sprintf('request %d should pass through under the limit', $i));
            self::assertSame('3', $response->getHeaderLine('X-RateLimit-Limit'));
            self::assertSame((string) (3 - $i - 1), $response->getHeaderLine('X-RateLimit-Remaining'));
        }
    }

    public function testRequestAtTheLimitReturnsFourTwoNineWithRetryAfterAndRateLimitHeaders(): void
    {
        $middleware = new RateLimitMiddleware(
            new InMemoryCache(),
            new RateLimit(limit: 2, windowSeconds: 60),
            new ResponseFactory(),
        );

        $middleware->process($this->request('1.2.3.4'), $this->okHandler());
        $middleware->process($this->request('1.2.3.4'), $this->okHandler());

        $blocked = $middleware->process($this->request('1.2.3.4'), $this->throwingHandler());

        self::assertSame(429, $blocked->getStatusCode());
        self::assertSame('2', $blocked->getHeaderLine('X-RateLimit-Limit'));
        self::assertSame('0', $blocked->getHeaderLine('X-RateLimit-Remaining'));
        self::assertNotSame('', $blocked->getHeaderLine('Retry-After'));
        self::assertGreaterThan(0, (int) $blocked->getHeaderLine('Retry-After'));
        self::assertGreaterThan(time(), (int) $blocked->getHeaderLine('X-RateLimit-Reset'));
    }

    public function testDifferentKeysGetIndependentBuckets(): void
    {
        $middleware = new RateLimitMiddleware(
            new InMemoryCache(),
            new RateLimit(limit: 1, windowSeconds: 60),
            new ResponseFactory(),
        );

        // Each IP gets its own bucket, so two distinct IPs both pass their first request.
        $first = $middleware->process($this->request('1.1.1.1'), $this->okHandler());
        $second = $middleware->process($this->request('2.2.2.2'), $this->okHandler());

        self::assertSame(200, $first->getStatusCode());
        self::assertSame(200, $second->getStatusCode());
    }

    public function testWindowResetClearsTheCounter(): void
    {
        // The cache TTL is the window; manually deleting the bucket key simulates
        // the window rolling over (the cache layer would have evicted it).
        $cache = new InMemoryCache();
        $middleware = new RateLimitMiddleware(
            $cache,
            new RateLimit(limit: 1, windowSeconds: 60),
            new ResponseFactory(),
        );

        $middleware->process($this->request('1.2.3.4'), $this->okHandler());

        $blocked = $middleware->process($this->request('1.2.3.4'), $this->throwingHandler());
        self::assertSame(429, $blocked->getStatusCode());

        $cache->clear();
        $afterReset = $middleware->process($this->request('1.2.3.4'), $this->okHandler());
        self::assertSame(200, $afterReset->getStatusCode());
    }

    public function testCustomKeyResolverDrivesTheBucketing(): void
    {
        $byApiKey = new class implements KeyResolverInterface {
            #[Override]
            public function resolve(ServerRequestInterface $request): string
            {
                $key = $request->getHeaderLine('X-Api-Key');

                return $key === '' ? 'anonymous' : 'api:' . $key;
            }
        };

        $middleware = new RateLimitMiddleware(
            new InMemoryCache(),
            new RateLimit(limit: 1, windowSeconds: 60),
            new ResponseFactory(),
            $byApiKey,
        );

        // Two requests from the SAME IP with DIFFERENT API keys both pass —
        // the limiter buckets on the API key, not the IP.
        $first = $middleware->process(
            $this->request('1.2.3.4')->withHeader('X-Api-Key', 'alpha'),
            $this->okHandler(),
        );
        $second = $middleware->process(
            $this->request('1.2.3.4')->withHeader('X-Api-Key', 'beta'),
            $this->okHandler(),
        );

        self::assertSame(200, $first->getStatusCode());
        self::assertSame(200, $second->getStatusCode());

        // A second request on the SAME API key is blocked, proving the
        // resolver — not the IP — is driving the bucket.
        $blocked = $middleware->process(
            $this->request('5.6.7.8')->withHeader('X-Api-Key', 'alpha'),
            $this->throwingHandler(),
        );
        self::assertSame(429, $blocked->getStatusCode());
    }

    public function testIpResolverPrefersTheTrustedAttributeOverRemoteAddr(): void
    {
        $resolver = new IpKeyResolver();

        $request = (new ServerRequest(['REMOTE_ADDR' => '1.2.3.4']))
            ->withAttribute(MiddlewareInterface::ATTRIBUTE_IP_ADDRESS, '9.9.9.9');

        self::assertSame('9.9.9.9', $resolver->resolve($request));
    }

    public function testRateLimitConstructorRejectsZeroOrNegativeValues(): void
    {
        self::expectException(InvalidArgumentException::class);
        new RateLimit(limit: 0, windowSeconds: 60);
    }

    private function request(string $remote): ServerRequestInterface
    {
        return new ServerRequest(['REMOTE_ADDR' => $remote], [], '/test', 'GET');
    }

    private function okHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            #[Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new ResponseFactory())->createResponse(200);
            }
        };
    }

    private function throwingHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            #[Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \LogicException('the handler must never be reached when the limit is exceeded');
            }
        };
    }
}

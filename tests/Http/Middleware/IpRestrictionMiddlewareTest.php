<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Middleware;

use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Middleware\IpRestrictionMiddleware;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class IpRestrictionMiddlewareTest extends AbstractMiddlewareTest
{
    public function testRequestPassesWhenNeitherAllowNorDenyConfigured(): void
    {
        $middleware = new IpRestrictionMiddleware(new ResponseFactory());
        $request = $this->requestWithIp('203.0.113.7');

        $response = $this->dispatch([$middleware, $this->okHandler()], $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDeniedIpReceives403(): void
    {
        $middleware = new IpRestrictionMiddleware(
            new ResponseFactory(),
            deny: ['203.0.113.0/24'],
        );

        $response = $this->dispatch(
            [$middleware, $this->okHandler()],
            $this->requestWithIp('203.0.113.7'),
        );

        $this->assertSame(HttpStatusCodeInterface::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testDenyTakesPrecedenceOverAllow(): void
    {
        $middleware = new IpRestrictionMiddleware(
            new ResponseFactory(),
            allow: ['203.0.113.0/24'],
            deny: ['203.0.113.7'],
        );

        $response = $this->dispatch(
            [$middleware, $this->okHandler()],
            $this->requestWithIp('203.0.113.7'),
        );

        $this->assertSame(HttpStatusCodeInterface::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testAllowListPermitsListedIps(): void
    {
        $middleware = new IpRestrictionMiddleware(
            new ResponseFactory(),
            allow: ['10.0.0.0/8'],
        );

        $response = $this->dispatch(
            [$middleware, $this->okHandler()],
            $this->requestWithIp('10.1.2.3'),
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testAllowListBlocksUnlistedIps(): void
    {
        $middleware = new IpRestrictionMiddleware(
            new ResponseFactory(),
            allow: ['10.0.0.0/8'],
        );

        $response = $this->dispatch(
            [$middleware, $this->okHandler()],
            $this->requestWithIp('203.0.113.7'),
        );

        $this->assertSame(HttpStatusCodeInterface::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testRequestWithoutIpAttributeIsBlockedWhenAllowListConfigured(): void
    {
        // Fail-closed: if an allowlist is in play and we don't know the IP, deny.
        $middleware = new IpRestrictionMiddleware(
            new ResponseFactory(),
            allow: ['10.0.0.0/8'],
        );

        $response = $this->dispatch(
            [$middleware, $this->okHandler()],
            new ServerRequest(),
        );

        $this->assertSame(HttpStatusCodeInterface::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testRequestWithoutIpAttributeIsAllowedWhenOnlyDenyConfigured(): void
    {
        // Fail-open: deny-only mode means anyone-not-denied passes; unknown IP isn't on the deny list.
        $middleware = new IpRestrictionMiddleware(
            new ResponseFactory(),
            deny: ['203.0.113.0/24'],
        );

        $response = $this->dispatch(
            [$middleware, $this->okHandler()],
            new ServerRequest(),
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCustomDeniedStatusCodeIsHonored(): void
    {
        $middleware = new IpRestrictionMiddleware(
            new ResponseFactory(),
            deny: ['203.0.113.0/24'],
            deniedStatusCode: HttpStatusCodeInterface::HTTP_UNAUTHORIZED,
        );

        $response = $this->dispatch(
            [$middleware, $this->okHandler()],
            $this->requestWithIp('203.0.113.7'),
        );

        $this->assertSame(HttpStatusCodeInterface::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testIpv6IsHandled(): void
    {
        $middleware = new IpRestrictionMiddleware(
            new ResponseFactory(),
            deny: ['2001:db8::/32'],
        );

        $response = $this->dispatch(
            [$middleware, $this->okHandler()],
            $this->requestWithIp('2001:db8::1'),
        );

        $this->assertSame(HttpStatusCodeInterface::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testAnyOfMultipleClientIpsBeingOnDenyListBlocksTheRequest(): void
    {
        // Some upstream IpAddressMiddleware setups collect REMOTE_ADDR + X-Forwarded-For
        // chain — if any link is a known bad actor, deny.
        $middleware = new IpRestrictionMiddleware(
            new ResponseFactory(),
            deny: ['198.51.100.0/24'],
        );

        $request = (new ServerRequest())->withAttribute(
            MiddlewareInterface::ATTRIBUTE_IP_ADDRESS,
            ['203.0.113.7', '198.51.100.4'],
        );

        $response = $this->dispatch([$middleware, $this->okHandler()], $request);

        $this->assertSame(HttpStatusCodeInterface::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testAllowListPassesIfAnyOfMultipleClientIpsMatches(): void
    {
        $middleware = new IpRestrictionMiddleware(
            new ResponseFactory(),
            allow: ['10.0.0.0/8'],
        );

        $request = (new ServerRequest())->withAttribute(
            MiddlewareInterface::ATTRIBUTE_IP_ADDRESS,
            ['203.0.113.7', '10.0.0.5'],
        );

        $response = $this->dispatch([$middleware, $this->okHandler()], $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    private function requestWithIp(string $ip): ServerRequestInterface
    {
        return (new ServerRequest())->withAttribute(MiddlewareInterface::ATTRIBUTE_IP_ADDRESS, [$ip]);
    }

    private function okHandler(): PsrMiddlewareInterface
    {
        return new class () implements PsrMiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new Response('php://temp', 200);
            }
        };
    }
}

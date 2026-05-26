<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Middleware;

use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Middleware\IpAddressMiddleware;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class IpAddressMiddlewareTest extends AbstractMiddlewareTest
{
    public function testIpFromRemoteAddrIsSetOnRequest(): void
    {
        $captured = null;
        $request = (new ServerRequest(['REMOTE_ADDR' => '203.0.113.7']));

        $this->dispatch([new IpAddressMiddleware(), $this->captureRequest($captured)], $request);

        $this->assertSame(['203.0.113.7'], $captured->getAttribute(MiddlewareInterface::ATTRIBUTE_IP_ADDRESS));
    }

    public function testIpsFromForwardedHeaderAreParsedAndDeduplicated(): void
    {
        $captured = null;
        $request = (new ServerRequest(
            ['REMOTE_ADDR' => '203.0.113.7'],
            [],
            null,
            null,
            'php://temp',
            ['X-Forwarded-For' => '203.0.113.7, 198.51.100.4'],
        ));

        $this->dispatch([new IpAddressMiddleware(), $this->captureRequest($captured)], $request);

        $ips = $captured->getAttribute(MiddlewareInterface::ATTRIBUTE_IP_ADDRESS);
        $this->assertSame(['203.0.113.7', '198.51.100.4'], $ips);
    }

    public function testInvalidIpsAreFilteredOut(): void
    {
        $captured = null;
        $request = (new ServerRequest(
            ['REMOTE_ADDR' => 'not-an-ip'],
            [],
            null,
            null,
            'php://temp',
            ['X-Forwarded-For' => '198.51.100.4, also-not-an-ip'],
        ));

        $this->dispatch([new IpAddressMiddleware(), $this->captureRequest($captured)], $request);

        $this->assertSame(['198.51.100.4'], $captured->getAttribute(MiddlewareInterface::ATTRIBUTE_IP_ADDRESS));
    }

    public function testEmptyServerYieldsEmptyIpList(): void
    {
        $captured = null;

        $this->dispatch([new IpAddressMiddleware(), $this->captureRequest($captured)], new ServerRequest());

        $this->assertSame([], $captured->getAttribute(MiddlewareInterface::ATTRIBUTE_IP_ADDRESS));
    }

    public function testCustomHeaderListIsHonored(): void
    {
        $captured = null;
        $request = (new ServerRequest(
            ['REMOTE_ADDR' => '203.0.113.7'],
            [],
            null,
            null,
            'php://temp',
            ['X-Forwarded-For' => '198.51.100.4'],
        ));

        // Custom list excludes X-Forwarded-For; only REMOTE_ADDR should appear
        $this->dispatch(
            [new IpAddressMiddleware(['Forwarded']), $this->captureRequest($captured)],
            $request,
        );

        $this->assertSame(['203.0.113.7'], $captured->getAttribute(MiddlewareInterface::ATTRIBUTE_IP_ADDRESS));
    }

    private function captureRequest(?ServerRequestInterface &$capture): PsrMiddlewareInterface
    {
        return new class ($capture) implements PsrMiddlewareInterface {
            public function __construct(private ?ServerRequestInterface &$capture)
            {
            }

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->capture = $request;

                return new Response('php://temp', 200);
            }
        };
    }
}

<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Dispatcher;

use Laminas\Diactoros\Response;
use Override;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Test PSR-18 client. Returns a configured status code, or throws a network
 * error, and captures the last request so signing headers can be asserted.
 */
final class FakeHttpClient implements ClientInterface
{
    public ?RequestInterface $lastRequest = null;

    public int $calls = 0;

    public function __construct(
        private readonly int $status = 200,
        private readonly bool $throwNetworkError = false,
    ) {
    }

    public static function returning(int $status): self
    {
        return new self($status);
    }

    public static function networkError(): self
    {
        return new self(0, true);
    }

    #[Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        ++$this->calls;
        $this->lastRequest = $request;

        if ($this->throwNetworkError) {
            throw new class('connection refused') extends RuntimeException implements ClientExceptionInterface {};
        }

        return new Response('php://memory', $this->status);
    }
}

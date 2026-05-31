<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Middleware;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\StreamFactory;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Test handler that records how many times it was invoked, so dedupe / replay
 * behaviour can assert "exactly one handler invocation".
 */
final class CountingHandler implements RequestHandlerInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly int $status = 201,
        private readonly string $body = '{"ok":true}',
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        ++$this->calls;

        return (new Response('php://memory', $this->status))
            ->withBody((new StreamFactory())->createStream($this->body));
    }
}

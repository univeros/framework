<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Idempotency\Middleware;

use Altair\Idempotency\Contracts\IdempotencyStoreInterface;
use Altair\Idempotency\Hash\RequestBodyHasher;
use Altair\Idempotency\Storage\StoredResponse;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Stripe-style Idempotency-Key middleware.
 *
 * Reads `Idempotency-Key` from the request, hashes the request body,
 * and coordinates with an {@see IdempotencyStoreInterface} so that a
 * replayed request returns the original response and a key reused
 * with a different payload is rejected with 409.
 *
 * Behaviour matrix:
 *
 * | Situation                                      | Response                              |
 * |------------------------------------------------|---------------------------------------|
 * | GET / HEAD / OPTIONS                           | Pass through; no caching.             |
 * | Header absent, mode=`optional`                 | Pass through; no caching.             |
 * | Header absent, mode=`required`                 | 400 with `{error}` envelope.          |
 * | Header malformed (>255 chars or ctrl/ws)       | 400 with `{error}` envelope.          |
 * | Key unseen                                     | Claim; execute; cache; return.        |
 * | Key seen, same hash, completed                 | Replay + `Idempotency-Replayed: true` |
 * | Key seen, same hash, in-progress (≤ maxWait)   | Wait + retry; replay when ready.      |
 * | Key seen, same hash, in-progress (> maxWait)   | 409 conflict.                         |
 * | Key seen, different hash                       | 409 conflict.                         |
 * | Handler throws                                 | Release claim; re-throw.              |
 * | Response is streaming                          | Pass through without caching.         |
 *
 * Response headers are stored on an allow-list basis (default
 * `Content-Type`, `Location`, `Link`) so that sensitive headers
 * (`Set-Cookie`, `Authorization`) never end up in shared storage.
 */
final readonly class IdempotencyKeyMiddleware implements MiddlewareInterface
{
    public const string MODE_OPTIONAL = 'optional';

    public const string MODE_REQUIRED = 'required';

    public const string HEADER_KEY = 'Idempotency-Key';

    public const string HEADER_REPLAYED = 'Idempotency-Replayed';

    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    private const array DEFAULT_ALLOWED_HEADERS = ['Content-Type', 'Location', 'Link'];

    /**
     * @param list<string> $allowedResponseHeaders Subset of response headers that should be replayed verbatim.
     */
    public function __construct(
        private IdempotencyStoreInterface $store,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private int $ttlSeconds,
        private string $mode = self::MODE_OPTIONAL,
        private RequestBodyHasher $hasher = new RequestBodyHasher(),
        private array $allowedResponseHeaders = self::DEFAULT_ALLOWED_HEADERS,
        private int $maxWaitMs = 500,
        private int $waitIntervalMs = 50,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (\in_array(strtoupper($request->getMethod()), self::SAFE_METHODS, true)) {
            return $handler->handle($request);
        }

        $key = $request->getHeaderLine(self::HEADER_KEY);
        if ($key === '') {
            if ($this->mode === self::MODE_REQUIRED) {
                return $this->errorResponse(400, 'Idempotency-Key header required for this endpoint.');
            }

            return $handler->handle($request);
        }

        if (!$this->isValidKey($key)) {
            return $this->errorResponse(400, 'Idempotency-Key header is malformed.');
        }

        $hash = $this->hasher->hash($request);
        $existing = $this->store->claim($key, $hash, $this->ttlSeconds);

        if (!$existing instanceof StoredResponse) {
            return $this->executeAndCache($key, $hash, $request, $handler);
        }

        if ($existing->requestHash !== $hash) {
            return $this->errorResponse(409, 'Idempotency-Key reused with a different payload.');
        }

        if (!$existing->inProgress) {
            return $this->replay($existing);
        }

        return $this->waitForInProgress($key);
    }

    private function executeAndCache(
        string $key,
        string $hash,
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        try {
            $response = $handler->handle($request);
        } catch (Throwable $throwable) {
            $this->store->release($key);

            throw $throwable;
        }

        if ($this->isStreaming($response)) {
            $this->store->release($key);

            return $response;
        }

        $bodyContents = (string) $response->getBody();
        $stored = StoredResponse::completed(
            requestHash: $hash,
            status: $response->getStatusCode(),
            headers: $this->filterHeaders($response),
            body: $bodyContents,
            createdAt: time(),
        );
        $this->store->complete($key, $stored, $this->ttlSeconds);

        // Rebuild the body so downstream output is not consumed.
        return $response->withBody($this->streamFactory->createStream($bodyContents));
    }

    private function waitForInProgress(string $key): ResponseInterface
    {
        $waited = 0;
        while ($waited < $this->maxWaitMs) {
            usleep($this->waitIntervalMs * 1000);
            $waited += $this->waitIntervalMs;

            $current = $this->store->get($key);
            if (!$current instanceof StoredResponse) {
                return $this->errorResponse(409, 'Idempotency-Key claim was released; retry the request.');
            }

            if (!$current->inProgress) {
                return $this->replay($current);
            }
        }

        return $this->errorResponse(409, 'Idempotency-Key claim is still in progress; retry later.');
    }

    private function replay(StoredResponse $stored): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($stored->status);
        foreach ($stored->headers as $name => $values) {
            foreach ($values as $value) {
                $response = $response->withAddedHeader($name, $value);
            }
        }

        $response = $response->withHeader(self::HEADER_REPLAYED, 'true');

        return $response->withBody($this->streamFactory->createStream($stored->body));
    }

    /**
     * @return array<string, list<string>>
     */
    private function filterHeaders(ResponseInterface $response): array
    {
        $kept = [];
        foreach ($this->allowedResponseHeaders as $name) {
            if (!$response->hasHeader($name)) {
                continue;
            }

            $kept[$name] = array_values($response->getHeader($name));
        }

        return $kept;
    }

    private function isStreaming(ResponseInterface $response): bool
    {
        if (strtolower($response->getHeaderLine('Transfer-Encoding')) === 'chunked') {
            return true;
        }

        $contentType = strtolower($response->getHeaderLine('Content-Type'));

        return str_starts_with($contentType, 'text/event-stream');
    }

    private function isValidKey(string $key): bool
    {
        if ($key === '' || \strlen($key) > 255) {
            return false;
        }

        // Reject ASCII control characters (including tab/newline) and any
        // whitespace; the spec leaves the rest of the printable set alone.
        return preg_match('/[\x00-\x20\x7F\s]/', $key) !== 1;
    }

    private function errorResponse(int $status, string $message): ResponseInterface
    {
        $body = json_encode(['error' => $message], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            $body = '{"error":"idempotency error"}';
        }

        $response = $this->responseFactory->createResponse($status);
        $response = $response->withHeader('Content-Type', 'application/json');

        return $response->withBody($this->streamFactory->createStream($body));
    }
}

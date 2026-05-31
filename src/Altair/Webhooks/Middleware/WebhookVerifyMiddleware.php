<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Middleware;

use Altair\Webhooks\Contracts\InboundDeduplicatorInterface;
use Altair\Webhooks\Contracts\SecretResolverInterface;
use Altair\Webhooks\Contracts\SignerInterface;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Inbound webhook verification.
 *
 * Reads the signature header, verifies it against the configured signer +
 * resolved secret, enforces a timestamp window for replay protection, dedupes
 * by event id, and either short-circuits (already processed) or passes the
 * verified payload to the handler.
 *
 * Behaviour matrix:
 *
 * | Situation                                   | Response                                  |
 * |---------------------------------------------|-------------------------------------------|
 * | Signature header absent / mismatch / secret | 401 with `{error}` (same opaque message). |
 * | Timestamp header absent (when required)     | 400 Bad Request.                          |
 * | Timestamp outside window (past or future)   | 400 `outside replay window`.              |
 * | Event id seen within dedupe TTL             | 200 OK, empty body, `Webhook-Replayed`.   |
 * | Fresh event                                 | Claim id; pass through; keep claim.       |
 * | Handler throws / returns 5xx                | Release the claim; re-throw / return.     |
 */
final readonly class WebhookVerifyMiddleware implements MiddlewareInterface
{
    public const string HEADER_REPLAYED = 'Webhook-Replayed';

    /** Opaque message for every signature failure — never leak which check failed. */
    private const string SIGNATURE_ERROR = 'webhook signature verification failed';

    public function __construct(
        private SignerInterface $signer,
        private SecretResolverInterface $secrets,
        private InboundDeduplicatorInterface $deduplicator,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private string $secretName,
        private int $dedupeTtlSeconds = 3600,
        private int $timestampWindowSeconds = 300,
        private bool $requireTimestamp = true,
        private string $signatureHeader = 'X-Signature',
        private string $timestampHeader = 'X-Timestamp',
        private string $eventIdHeader = 'X-Event-Id',
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $body = (string) $request->getBody();
        // Rebuild the body so downstream handlers read it from position 0.
        $request = $request->withBody($this->streamFactory->createStream($body));

        $signature = $request->getHeaderLine($this->signatureHeader);
        if ($signature === '') {
            return $this->error(401, self::SIGNATURE_ERROR);
        }

        $secret = $this->secrets->resolve($this->secretName);
        if (!$this->signer->verify($body, $signature, $secret)) {
            return $this->error(401, self::SIGNATURE_ERROR);
        }

        $timestamp = $request->getHeaderLine($this->timestampHeader);
        $timestampError = $this->checkTimestamp($timestamp);
        if ($timestampError !== null) {
            return $this->error(400, $timestampError);
        }

        $eventId = $this->resolveEventId($request, $body, $timestamp);
        if (!$this->deduplicator->claim($eventId, $this->dedupeTtlSeconds)) {
            return $this->replayed();
        }

        try {
            $response = $handler->handle($request);
        } catch (Throwable $throwable) {
            $this->deduplicator->release($eventId);

            throw $throwable;
        }

        // A failed handler (5xx) should let the sender retry rather than be
        // absorbed as a duplicate, so the claim is dropped.
        if ($response->getStatusCode() >= 500) {
            $this->deduplicator->release($eventId);
        }

        return $response;
    }

    private function checkTimestamp(string $timestamp): ?string
    {
        if ($timestamp === '') {
            return $this->requireTimestamp ? 'missing timestamp' : null;
        }

        if (preg_match('/^\d+$/', $timestamp) !== 1) {
            return 'invalid timestamp';
        }

        $delta = abs(time() - (int) $timestamp);
        if ($delta > $this->timestampWindowSeconds) {
            return 'outside replay window';
        }

        return null;
    }

    private function resolveEventId(ServerRequestInterface $request, string $body, string $timestamp): string
    {
        $eventId = $request->getHeaderLine($this->eventIdHeader);
        if ($eventId !== '') {
            return $eventId;
        }

        // No stable id supplied — synthesise one from the body + timestamp.
        return hash('sha256', $body . '|' . $timestamp);
    }

    private function replayed(): ResponseInterface
    {
        return $this->responseFactory->createResponse(200)
            ->withHeader(self::HEADER_REPLAYED, 'true')
            ->withBody($this->streamFactory->createStream(''));
    }

    private function error(int $status, string $message): ResponseInterface
    {
        $body = json_encode(['error' => $message], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            $body = '{"error":"webhook error"}';
        }

        return $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream($body));
    }
}

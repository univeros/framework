<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory\Http;

use Altair\Events\Event;
use Altair\Events\Reader;
use Altair\Observatory\Observatory;
use JsonException;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Server-Sent Events endpoint for the live activity tail.
 *
 * Rather than holding a long-lived connection (which would pin a PHP worker),
 * this emits the events newer than the client's `Last-Event-ID` and closes; the
 * browser's EventSource reconnects after `retry` ms with the last id it saw, so
 * the stream stays near-real-time with zero extra infrastructure. Events are
 * emitted oldest-first so the client can prepend each row and keep the newest on
 * top. Access is gated by the Observatory facade.
 */
final readonly class ActivityStreamHandler implements RequestHandlerInterface
{
    public function __construct(
        private Observatory $observatory,
        private Reader $reader,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private int $retryMs = 2000,
        private int $backlog = 50,
    ) {}

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->observatory->isAccessible()) {
            return $this->responseFactory->createResponse(403);
        }

        $lastId = $this->lastEventId($request);

        $events = $lastId === ''
            ? iterator_to_array($this->reader->tail($this->backlog), false)
            : iterator_to_array($this->reader->sinceId($lastId), false);

        $body = \sprintf("retry: %d\n\n", $this->retryMs);
        // Readers yield newest-first; reverse to oldest-first for prepend ordering.
        foreach (array_reverse($events) as $event) {
            $body .= $this->formatEvent($event);
        }

        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/event-stream')
            ->withHeader('Cache-Control', 'no-cache')
            ->withHeader('X-Accel-Buffering', 'no')
            ->withBody($this->streamFactory->createStream($body));
    }

    private function lastEventId(ServerRequestInterface $request): string
    {
        $header = $request->getHeaderLine('Last-Event-ID');

        if ($header !== '') {
            return $header;
        }

        $lastId = $request->getQueryParams()['lastId'] ?? null;

        return \is_string($lastId) ? $lastId : '';
    }

    private function formatEvent(Event $event): string
    {
        $array = $event->toArray();

        try {
            $data = json_encode($array, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            return '';
        }

        $id = isset($array['id']) && \is_scalar($array['id']) ? (string) $array['id'] : '';

        return \sprintf("id: %s\nevent: activity\ndata: %s\n\n", $id, $data);
    }
}

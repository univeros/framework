<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observability\Middleware;

use Altair\Observability\Metrics\Meter;
use Altair\Observability\Trace\SpanKind;
use Altair\Observability\Trace\SpanStatus;
use Altair\Observability\Trace\Tracer;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Per-request span at the top of the HTTP pipeline.
 *
 * Each request opens a server-kind span named `HTTP <METHOD>`, attaches
 * standard HTTP attributes (method, target, scheme, status), and emits an
 * `http.server.request.duration` histogram on completion. An uncaught
 * downstream exception ends the span with `Error` status and the exception
 * type/message as attributes — then re-throws so the framework's own error
 * handling runs unchanged.
 *
 * The span context is exposed via the `Tracer` so deeper instrumentation can
 * open child spans without taking the tracer as a parameter.
 */
final readonly class ObservabilityMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Tracer $tracer,
        private ?Meter $meter = null,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $target = $request->getRequestTarget();
        $scheme = $request->getUri()->getScheme();

        $context = $this->tracer->start(
            'HTTP ' . $method,
            SpanKind::Server,
            [
                'http.request.method' => $method,
                'url.scheme' => $scheme,
                'url.path' => $request->getUri()->getPath(),
                'http.target' => $target,
            ],
        );

        try {
            $response = $handler->handle($request);
        } catch (Throwable $throwable) {
            $this->tracer->end($context, SpanStatus::Error, $throwable->getMessage(), [
                'exception.type' => $throwable::class,
                'exception.message' => $throwable->getMessage(),
            ]);
            $this->meter?->counter('http.server.requests', 1.0, [
                'http.method' => $method,
                'http.status_code' => 500,
                'error' => true,
            ]);

            throw $throwable;
        }

        $status = $response->getStatusCode();
        $this->tracer->end(
            $context,
            $status >= 500 ? SpanStatus::Error : SpanStatus::Ok,
            null,
            ['http.response.status_code' => $status],
        );

        if ($this->meter instanceof Meter) {
            $this->meter->counter('http.server.requests', 1.0, [
                'http.method' => $method,
                'http.status_code' => $status,
            ]);
        }

        return $response;
    }
}

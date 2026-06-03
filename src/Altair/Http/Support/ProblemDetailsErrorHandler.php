<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Support;

use Altair\Http\Contracts\ErrorHandlerInterface;
use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Contracts\ProblemExtensionInterface;
use JsonException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Production-grade, agent-native error renderer.
 *
 * Emits an RFC 7807 `application/problem+json` document by default (typed,
 * machine-branchable), negotiating down to a safe HTML page or plain text
 * when the client asks for them. Unlike the legacy {@see DefaultErrorHandler}
 * it writes to the PSR-7 body (never `echo`), escapes every interpolated
 * value, and distinguishes production from debug:
 *
 *  - production: generic detail for 5xx, never the exception class or trace
 *  - debug:      exception class, file:line and stack trace attached
 *
 * The status code and any headers are expected to already be on the response
 * (the {@see \Altair\Http\Middleware\ExceptionHandlerMiddleware} sets them from
 * the thrown exception); this handler only renders a body for that status.
 */
final readonly class ProblemDetailsErrorHandler implements ErrorHandlerInterface
{
    private const string PROBLEM_TYPE = 'about:blank';

    /**
     * Reserved RFC 7807 members an exception's extensions must not overwrite.
     */
    private const array RESERVED_MEMBERS = ['type', 'title', 'status', 'instance'];

    public function __construct(
        private bool $debug = false,
        private string $serverErrorDetail = 'An unexpected error occurred.',
    ) {}

    #[Override]
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $exception = $request->getAttribute(MiddlewareInterface::ATTRIBUTE_EXCEPTION);
        $exception = $exception instanceof Throwable ? $exception : null;

        $problem = $this->buildProblem($request, $response, $exception);

        return match ($this->negotiate($request->getHeaderLine('Accept'))) {
            'html' => $this->renderHtml($response, $problem),
            'text' => $this->renderText($response, $problem),
            default => $this->renderJson($response, $problem),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProblem(
        ServerRequestInterface $request,
        ResponseInterface $response,
        ?Throwable $exception,
    ): array {
        $status = $response->getStatusCode();
        $reason = $response->getReasonPhrase();

        $problem = [
            'type' => self::PROBLEM_TYPE,
            'title' => $reason !== '' ? $reason : 'Error',
            'status' => $status,
        ];

        $detail = $this->detailFor($status, $exception);
        if ($detail !== null) {
            $problem['detail'] = $detail;
        }

        $path = $request->getUri()->getPath();
        if ($path !== '') {
            $problem['instance'] = $path;
        }

        if ($exception instanceof ProblemExtensionInterface) {
            foreach ($exception->getProblemExtensions() as $key => $value) {
                if (!\in_array($key, self::RESERVED_MEMBERS, true)) {
                    $problem[$key] = $value;
                }
            }
        }

        if ($this->debug && $exception instanceof Throwable) {
            $problem['exception'] = $exception::class;
            $problem['file'] = $exception->getFile() . ':' . $exception->getLine();
            $problem['trace'] = explode("\n", $exception->getTraceAsString());
        }

        return $problem;
    }

    /**
     * Server errors never leak their internal message in production.
     */
    private function detailFor(int $status, ?Throwable $exception): ?string
    {
        if (!$exception instanceof Throwable) {
            return null;
        }

        if ($status >= HttpStatusCodeInterface::HTTP_INTERNAL_SERVER_ERROR && !$this->debug) {
            return $this->serverErrorDetail;
        }

        $message = $exception->getMessage();

        return $message !== '' ? $message : null;
    }

    private function negotiate(string $accept): string
    {
        $accept = strtolower($accept);

        if ($accept === '' || str_contains($accept, '+json') || str_contains($accept, 'application/json')) {
            return 'json';
        }

        if (str_contains($accept, 'text/html') || str_contains($accept, 'application/xhtml')) {
            return 'html';
        }

        if (str_contains($accept, 'text/plain')) {
            return 'text';
        }

        // `*/*` or anything unrecognised: this is an API-first framework.
        return 'json';
    }

    /**
     * @param array<string, mixed> $problem
     */
    private function renderJson(ResponseInterface $response, array $problem): ResponseInterface
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | ($this->debug ? JSON_PRETTY_PRINT : 0);

        try {
            $body = json_encode($problem, JSON_THROW_ON_ERROR | $flags);
        } catch (JsonException) {
            $body = json_encode([
                'type' => self::PROBLEM_TYPE,
                'title' => $problem['title'] ?? 'Error',
                'status' => $problem['status'] ?? HttpStatusCodeInterface::HTTP_INTERNAL_SERVER_ERROR,
            ]) ?: '{"type":"about:blank","title":"Error","status":500}';
        }

        $response->getBody()->write($body);

        return $response->withHeader('Content-Type', 'application/problem+json');
    }

    /**
     * @param array<string, mixed> $problem
     */
    private function renderHtml(ResponseInterface $response, array $problem): ResponseInterface
    {
        $status = (int) ($problem['status'] ?? HttpStatusCodeInterface::HTTP_INTERNAL_SERVER_ERROR);
        $title = $this->escape((string) ($problem['title'] ?? 'Error'));
        $detail = isset($problem['detail']) ? '<p>' . $this->escape((string) $problem['detail']) . '</p>' : '';
        $debug = $this->debug && isset($problem['exception'])
            ? \sprintf(
                "<pre>%s\n%s\n\n%s</pre>",
                $this->escape((string) $problem['exception']),
                $this->escape((string) ($problem['file'] ?? '')),
                $this->escape(implode("\n", (array) ($problem['trace'] ?? []))),
            )
            : '';

        $body = <<<HTML
            <!doctype html>
            <html lang="en">
            <head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{$status} {$title}</title>
            <style>html{font-family:system-ui,sans-serif;margin:2rem;color:#1a1a1a}h1{font-size:1.5rem}pre{background:#f5f5f5;padding:1rem;overflow:auto;border-radius:6px}</style>
            </head>
            <body><h1>{$status} {$title}</h1>{$detail}{$debug}</body>
            </html>
            HTML;

        $response->getBody()->write($body);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * @param array<string, mixed> $problem
     */
    private function renderText(ResponseInterface $response, array $problem): ResponseInterface
    {
        $status = (int) ($problem['status'] ?? HttpStatusCodeInterface::HTTP_INTERNAL_SERVER_ERROR);
        $body = \sprintf('%d %s', $status, (string) ($problem['title'] ?? 'Error'));

        if (isset($problem['detail'])) {
            $detail = (string) $problem['detail'];
            $body .= "\n\n" . $detail;
        }

        $response->getBody()->write($body);

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events;

use Altair\Events\Contracts\RecorderInterface;
use Override;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;
use Throwable;

/**
 * A PSR-3 logger that turns serious log records into `http_error` events.
 *
 * This is the bridge that gives an agent durable, queryable memory of runtime
 * failures (`bin/altair events:filter --kind=http_error`) without coupling the
 * Http package to Events: the front controller hands the
 * {@see \Altair\Http\Middleware\ExceptionHandlerMiddleware} any PSR-3 logger,
 * and this implementation is the one that records.
 *
 * Only `error` and above are recorded; lighter levels are dropped so the log
 * stays a signal of genuine server-side failures.
 */
final class EventRecordingLogger extends AbstractLogger
{
    /**
     * @var array<string, true>
     */
    private const array RECORDED_LEVELS = [
        LogLevel::EMERGENCY => true,
        LogLevel::ALERT => true,
        LogLevel::CRITICAL => true,
        LogLevel::ERROR => true,
    ];

    public function __construct(
        private readonly RecorderInterface $recorder,
        private readonly Actor $actor = Actor::Script,
    ) {}

    /**
     * @param mixed                $level
     * @param array<string, mixed> $context
     */
    #[Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (!\is_string($level) || !isset(self::RECORDED_LEVELS[$level])) {
            return;
        }

        $this->recorder->record(Event::create(
            actor: $this->actor,
            command: $this->commandFrom($context),
            kind: EventKind::HttpError,
            status: EventStatus::Fail,
            durationMs: 0,
            error: $this->errorFrom($message, $context),
            extra: $this->extraFrom($context),
        ));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function commandFrom(array $context): string
    {
        $method = isset($context['method']) && \is_string($context['method']) ? $context['method'] : '';
        $path = isset($context['path']) && \is_string($context['path']) ? $context['path'] : '';
        $command = trim($method . ' ' . $path);

        return $command !== '' ? $command : 'http.request';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function errorFrom(string|Stringable $message, array $context): string
    {
        $error = trim((string) $message);
        if ($error !== '') {
            return $error;
        }

        if (($context['exception'] ?? null) instanceof Throwable) {
            return $context['exception']::class;
        }

        return 'HTTP server error';
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function extraFrom(array $context): array
    {
        $extra = [];

        foreach (['method', 'path', 'status'] as $key) {
            if (isset($context[$key]) && (\is_string($context[$key]) || \is_int($context[$key]))) {
                $extra[$key] = $context[$key];
            }
        }

        if (($context['exception'] ?? null) instanceof Throwable) {
            $extra['exception'] = $context['exception']::class;
        }

        return $extra;
    }
}

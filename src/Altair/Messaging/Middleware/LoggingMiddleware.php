<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Messaging\Middleware;

use Override;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Throwable;

/**
 * Optional dispatch-side logging middleware. Off by default; opt in by
 * inserting it into the middleware list passed to the bus.
 */
final readonly class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(private LoggerInterface $logger = new NullLogger()) {}

    #[Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $messageClass = $envelope->getMessage()::class;
        $this->logger->info('Messenger dispatching {class}', ['class' => $messageClass]);

        try {
            $result = $stack->next()->handle($envelope, $stack);
        } catch (Throwable $throwable) {
            $this->logger->error('Messenger dispatch failed for {class}: {error}', [
                'class' => $messageClass,
                'error' => $throwable->getMessage(),
                'exception' => $throwable,
            ]);

            throw $throwable;
        }

        $this->logger->debug('Messenger dispatched {class}', ['class' => $messageClass]);

        return $result;
    }
}

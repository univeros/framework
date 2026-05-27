<?php

declare(strict_types=1);

namespace Altair\Tests\Messaging\Middleware;

use Altair\Messaging\Middleware\LoggingMiddleware;
use Altair\Tests\Messaging\Fixtures\PingMessage;
use Override;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

class LoggingMiddlewareTest extends TestCase
{
    public function testLogsDispatchSuccess(): void
    {
        $logger = $this->createCollectingLogger();
        $middleware = new LoggingMiddleware($logger);

        $envelope = new Envelope(new PingMessage('hi'));
        $stack = $this->stackThatReturns($envelope);

        $result = $middleware->handle($envelope, $stack);

        $this->assertSame($envelope, $result);
        $this->assertNotEmpty($logger->records);
        $this->assertSame('info', $logger->records[0]['level']);
        $this->assertSame('debug', $logger->records[1]['level']);
    }

    public function testLogsAndRethrowsOnFailure(): void
    {
        $logger = $this->createCollectingLogger();
        $middleware = new LoggingMiddleware($logger);

        $envelope = new Envelope(new PingMessage('boom'));
        $stack = $this->stackThatThrows(new RuntimeException('boom'));

        try {
            $middleware->handle($envelope, $stack);
            $this->fail('Expected RuntimeException to propagate.');
        } catch (RuntimeException $runtimeException) {
            $this->assertSame('boom', $runtimeException->getMessage());
        }

        $errorLevels = array_column($logger->records, 'level');
        $this->assertContains('error', $errorLevels);
    }

    private function createCollectingLogger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<array{level: string, message: string}> */
            public array $records = [];

            #[Override]
            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => (string) $level, 'message' => (string) $message];
            }
        };
    }

    private function stackThatReturns(Envelope $returned): StackInterface
    {
        $middleware = new class($returned) implements MiddlewareInterface {
            public function __construct(private readonly Envelope $returned) {}

            #[Override]
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $this->returned;
            }
        };

        return $this->stackOf($middleware);
    }

    private function stackThatThrows(\Throwable $throwable): StackInterface
    {
        $middleware = new class($throwable) implements MiddlewareInterface {
            public function __construct(private readonly \Throwable $throwable) {}

            #[Override]
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                throw $this->throwable;
            }
        };

        return $this->stackOf($middleware);
    }

    private function stackOf(MiddlewareInterface $middleware): StackInterface
    {
        return new class($middleware) implements StackInterface {
            public function __construct(private readonly MiddlewareInterface $middleware) {}

            #[Override]
            public function next(): MiddlewareInterface
            {
                return $this->middleware;
            }
        };
    }
}

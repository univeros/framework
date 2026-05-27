<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Messaging\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Messaging\Worker\WorkerFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use const SIGINT;
use const SIGTERM;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\EventListener\DispatchPcntlSignalListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMemoryLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnTimeLimitListener;
use Symfony\Component\Messenger\Worker;

/**
 * `bin/altair worker` — consume messages from one or more transports.
 *
 * Graceful shutdown: registers SIGTERM/SIGINT listeners so a running
 * worker drains the in-flight message before exiting.
 */
#[Command(
    name: 'worker',
    description: 'Consume messages from configured Messenger transports.',
)]
final readonly class WorkerCommand
{
    public function __construct(
        private WorkerFactory $factory,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function __invoke(
        #[Option(description: 'Comma-separated transport names; defaults to every configured transport.', name: 'transports')]
        ?string $transports = null,
        #[Option(description: 'Stop after consuming N messages.', name: 'limit')]
        ?int $limit = null,
        #[Option(description: 'Stop after N seconds (good for systemd / Kubernetes).', name: 'time-limit')]
        ?int $timeLimit = null,
        #[Option(description: 'Stop when memory usage exceeds the given bytes (e.g. 128M).', name: 'memory-limit')]
        ?string $memoryLimit = null,
        #[Option(description: 'Microseconds to sleep when no messages are available.', name: 'sleep')]
        int $sleep = 1_000_000,
    ): int {
        $transportList = $transports === null
            ? null
            : array_values(array_filter(array_map('trim', explode(',', $transports))));

        $worker = $this->factory->create($transportList);

        $this->registerStopListeners($worker, $limit, $timeLimit, $memoryLimit);

        $this->logger->info('Starting worker on transports: {transports}', [
            'transports' => implode(',', $transportList ?? ['(all)']),
        ]);

        $worker->run([
            'sleep' => $sleep,
            'time_limit' => $timeLimit,
        ]);

        return 0;
    }

    private function registerStopListeners(Worker $worker, ?int $limit, ?int $timeLimit, ?string $memoryLimit): void
    {
        if (\function_exists('pcntl_signal')) {
            $this->eventDispatcher->addSubscriber(new DispatchPcntlSignalListener());
            $stop = static function () use ($worker): void {
                $worker->stop();
            };
            pcntl_signal(SIGTERM, $stop);
            pcntl_signal(SIGINT, $stop);
            pcntl_async_signals(true);
        }

        if ($limit !== null && $limit > 0) {
            $this->eventDispatcher->addSubscriber(new StopWorkerOnMessageLimitListener($limit, $this->logger));
        }

        if ($timeLimit !== null && $timeLimit > 0) {
            $this->eventDispatcher->addSubscriber(new StopWorkerOnTimeLimitListener($timeLimit, $this->logger));
        }

        if ($memoryLimit !== null && $memoryLimit !== '') {
            $bytes = $this->parseMemoryLimit($memoryLimit);
            $this->eventDispatcher->addSubscriber(new StopWorkerOnMemoryLimitListener($bytes, $this->logger));
        }
    }

    private function parseMemoryLimit(string $value): int
    {
        $value = trim($value);
        if (!preg_match('/^(\d+)([KMG]?)$/i', $value, $matches)) {
            return (int) $value;
        }

        $size = (int) $matches[1];

        return match (strtoupper($matches[2])) {
            'K' => $size * 1024,
            'M' => $size * 1024 * 1024,
            'G' => $size * 1024 * 1024 * 1024,
            default => $size,
        };
    }
}

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
use Altair\Messaging\Configuration\TransportSettings;
use Altair\Messaging\Contracts\MessageBusInterface;
use Altair\Messaging\Exception\InvalidArgumentException;
use Altair\Messaging\Transport\TransportRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;

/**
 * `bin/altair worker:retry-failed` — drain envelopes from the failure
 * transport and re-dispatch them through the bus.
 */
#[Command(
    name: 'worker:retry-failed',
    description: 'Re-dispatch messages currently in the failure transport.',
)]
final readonly class FailedRetryCommand
{
    public function __construct(
        private TransportSettings $settings,
        private TransportRegistry $registry,
        private MessageBusInterface $bus,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function __invoke(
        #[Option(description: 'Maximum number of envelopes to retry in this run.', name: 'limit')]
        int $limit = 1000,
    ): int {
        $transportName = $this->settings->failureTransport
            ?? throw new InvalidArgumentException('No failure transport configured. Set MESSENGER_FAILURE_TRANSPORT=name.');

        $receiver = $this->registry->get($transportName);
        if (!$receiver instanceof ListableReceiverInterface) {
            echo "Failure transport '{$transportName}' does not support listing (cannot drain).\n";

            return 1;
        }

        $retried = 0;
        foreach ($receiver->all($limit) as $envelope) {
            $cleaned = $envelope->withoutAll(SentToFailureTransportStamp::class);
            $this->bus->dispatch($cleaned->getMessage(), array_values($cleaned->all()[''] ?? []));
            $receiver->ack($envelope);

            $retried++;
            $this->logger->info('Retried failed message {class}', ['class' => $envelope->getMessage()::class]);
        }

        echo "Retried {$retried} message(s) from '{$transportName}'.\n";

        return 0;
    }
}

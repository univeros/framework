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
use Altair\Messaging\Exception\InvalidArgumentException;
use Altair\Messaging\Transport\TransportRegistry;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;

/**
 * `bin/altair worker:show-failed` — list envelopes currently held in
 * the configured failure transport.
 */
#[Command(
    name: 'worker:show-failed',
    description: 'List messages currently in the configured failure transport.',
)]
final readonly class FailedShowCommand
{
    public function __construct(
        private TransportSettings $settings,
        private TransportRegistry $registry,
    ) {}

    public function __invoke(
        #[Option(description: 'Maximum number of envelopes to list.', name: 'limit')]
        int $limit = 50,
    ): int {
        $transportName = $this->settings->failureTransport
            ?? throw new InvalidArgumentException('No failure transport configured. Set MESSENGER_FAILURE_TRANSPORT=name.');

        $receiver = $this->registry->get($transportName);
        if (!$receiver instanceof ListableReceiverInterface) {
            echo "Failure transport '{$transportName}' does not support listing.\n";

            return 1;
        }

        $count = 0;
        foreach ($receiver->all($limit) as $envelope) {
            $count++;
            $message = $envelope->getMessage();
            echo \sprintf("- %s\n", $message::class);
        }

        echo "Found {$count} failed message(s) in '{$transportName}'.\n";

        return 0;
    }
}

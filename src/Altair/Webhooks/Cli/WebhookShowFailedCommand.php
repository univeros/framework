<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Cli;

use Altair\Webhooks\Contracts\DeliveryStoreInterface;
use Altair\Webhooks\Storage\Delivery;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'webhook:show-failed', description: 'List dead-lettered webhook deliveries')]
final class WebhookShowFailedCommand extends Command
{
    public function __construct(
        private readonly DeliveryStoreInterface $deliveries,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of deliveries to list', '100');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $limitOption = $input->getOption('limit');
        $limit = is_numeric($limitOption) ? max(1, (int) $limitOption) : 100;

        $failed = $this->deliveries->findFailed($limit);
        if ($failed === []) {
            $style->success('No dead-lettered deliveries.');

            return Command::SUCCESS;
        }

        $style->table(
            ['Delivery', 'Event', 'Subscriber', 'Attempts', 'Last response'],
            array_map(
                static fn(Delivery $delivery): array => [
                    $delivery->id,
                    $delivery->eventName,
                    $delivery->subscriberUrl,
                    (string) $delivery->attempts,
                    $delivery->lastResponse ?? '',
                ],
                $failed,
            ),
        );

        return Command::SUCCESS;
    }
}

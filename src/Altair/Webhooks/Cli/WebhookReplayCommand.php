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
use Altair\Webhooks\Dispatcher\WebhookDispatcher;
use Altair\Webhooks\Storage\Delivery;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'webhook:replay', description: 'Re-dispatch a failed / dead-lettered webhook delivery')]
final class WebhookReplayCommand extends Command
{
    public function __construct(
        private readonly DeliveryStoreInterface $deliveries,
        private readonly WebhookDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('delivery-id', InputArgument::REQUIRED, 'Delivery id (or an unambiguous prefix)');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $argument = $input->getArgument('delivery-id');
        $id = \is_string($argument) ? $argument : '';

        $delivery = $this->resolve($id);
        if (!$delivery instanceof Delivery) {
            $style->error(\sprintf('No delivery matching "%s".', $id));

            return Command::FAILURE;
        }

        $reset = $this->dispatcher->redispatch($delivery);
        $style->success(\sprintf('Re-dispatched delivery %s (reset to pending).', $reset->id));

        return Command::SUCCESS;
    }

    private function resolve(string $id): ?Delivery
    {
        if ($id === '') {
            return null;
        }

        $exact = $this->deliveries->findById($id);
        if ($exact instanceof Delivery) {
            return $exact;
        }

        // Fall back to a unique-prefix match among dead-lettered deliveries —
        // the realistic replay target.
        $matches = array_values(array_filter(
            $this->deliveries->findFailed(1000),
            static fn(Delivery $delivery): bool => str_starts_with($delivery->id, $id),
        ));

        return \count($matches) === 1 ? $matches[0] : null;
    }
}

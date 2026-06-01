<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Cli;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Webhooks\Contracts\DeliveryStoreInterface;
use Altair\Webhooks\Dispatcher\WebhookDispatcher;
use Altair\Webhooks\Storage\Delivery;

/**
 * `bin/altair webhook:replay <delivery-id>` — re-dispatch a dead-lettered
 * delivery. Resets the delivery to pending (attempts 0) and puts a fresh
 * {@see \Altair\Webhooks\Dispatcher\WebhookMessage} back on the bus.
 *
 * Exit code is `1` when no delivery matches the id (or prefix), otherwise `0`.
 */
#[Command(
    name: 'webhook:replay',
    description: 'Re-dispatch a failed / dead-lettered webhook delivery.',
)]
final readonly class WebhookReplayCommand
{
    public function __construct(
        private DeliveryStoreInterface $deliveries,
        private WebhookDispatcher $dispatcher,
    ) {}

    public function __invoke(
        #[Argument(description: 'Delivery id (or an unambiguous prefix).')]
        string $deliveryId = '',
    ): int {
        $delivery = $this->resolve($deliveryId);
        if (!$delivery instanceof Delivery) {
            echo \sprintf("No delivery matching \"%s\".\n", $deliveryId);

            return 1;
        }

        $reset = $this->dispatcher->redispatch($delivery);
        echo \sprintf("Re-dispatched delivery %s (reset to pending).\n", $reset->id);

        return 0;
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

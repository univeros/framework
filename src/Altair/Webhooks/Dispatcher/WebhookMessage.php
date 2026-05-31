<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Dispatcher;

/**
 * The DTO that travels through Symfony Messenger from the dispatcher to the
 * worker-side {@see WebhookHandler}. Carries everything needed to rebuild and
 * sign the outbound POST; the delivery state lives in the DeliveryStore.
 */
final readonly class WebhookMessage
{
    public function __construct(
        public string $deliveryId,
        public string $eventName,
        public string $payload,
        public string $subscriberUrl,
        public string $secretName,
        public string $signerName,
    ) {}
}

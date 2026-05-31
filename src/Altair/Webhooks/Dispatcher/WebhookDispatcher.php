<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Dispatcher;

use Altair\Messaging\Contracts\MessageBusInterface;
use Altair\Webhooks\Contracts\DeliveryStoreInterface;
use Altair\Webhooks\Exception\WebhookException;
use Altair\Webhooks\Storage\Delivery;
use JsonException;
use Symfony\Component\Uid\Ulid;

/**
 * Application-facing API for emitting outbound webhooks.
 *
 * Records a Pending {@see Delivery} row and dispatches a {@see WebhookMessage}
 * onto the bus; the worker-side {@see WebhookHandler} performs the signed POST,
 * applies the retry curve, and dead-letters after the threshold.
 */
final readonly class WebhookDispatcher
{
    public function __construct(
        private MessageBusInterface $bus,
        private DeliveryStoreInterface $deliveries,
        private string $defaultSignerName = 'hmac-sha256',
    ) {}

    /**
     * Emit a webhook. The payload may be a JSON string or an array (encoded to
     * JSON). Returns the recorded Delivery so the caller can persist its id.
     *
     * @param array<array-key, mixed>|string $payload
     */
    public function dispatch(
        string $eventName,
        array|string $payload,
        string $subscriberUrl,
        string $secretName,
        ?string $signerName = null,
    ): Delivery {
        $delivery = Delivery::create(
            id: (string) new Ulid(),
            eventName: $eventName,
            subscriberUrl: $subscriberUrl,
            payload: $this->normalisePayload($payload),
            secretName: $secretName,
            signerName: $signerName ?? $this->defaultSignerName,
            createdAt: time(),
        );

        $this->deliveries->record($delivery);
        $this->bus->dispatch($this->messageFor($delivery));

        return $delivery;
    }

    /**
     * Re-dispatch an existing delivery (used by webhook:replay). Resets the
     * attempt counter + status to Pending and puts the same payload back on the
     * bus.
     */
    public function redispatch(Delivery $delivery): Delivery
    {
        $reset = $delivery->reset();
        $this->deliveries->update($reset);
        $this->bus->dispatch($this->messageFor($reset));

        return $reset;
    }

    private function messageFor(Delivery $delivery): WebhookMessage
    {
        return new WebhookMessage(
            deliveryId: $delivery->id,
            eventName: $delivery->eventName,
            payload: $delivery->payload,
            subscriberUrl: $delivery->subscriberUrl,
            secretName: $delivery->secretName,
            signerName: $delivery->signerName,
        );
    }

    /**
     * @param array<array-key, mixed>|string $payload
     */
    private function normalisePayload(array|string $payload): string
    {
        if (\is_string($payload)) {
            return $payload;
        }

        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $jsonException) {
            throw new WebhookException('Webhook payload is not JSON-encodable: ' . $jsonException->getMessage(), 0, $jsonException);
        }
    }
}

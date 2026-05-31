<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Dispatcher;

use Altair\Messaging\Attribute\AsHandler;
use Altair\Webhooks\Contracts\DeliveryStoreInterface;
use Altair\Webhooks\Contracts\SecretResolverInterface;
use Altair\Webhooks\Signing\SignerRegistry;
use Altair\Webhooks\Storage\Delivery;
use Altair\Webhooks\Storage\DeliveryStatus;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Worker-side handler that performs the signed outbound POST and records the
 * delivery outcome.
 *
 * Retry strategy: the DeliveryStore is the source of truth for the attempt
 * counter (not Messenger's redelivery count). On a transient failure (5xx or
 * network error) below the threshold, the row is marked Failed with the next
 * attempt time stamped from {@see RetryPolicy}, and a
 * RecoverableMessageHandlingException is thrown so Messenger redelivers. At the
 * threshold the row is marked DeadLettered and an
 * UnrecoverableMessageHandlingException routes the envelope to the failure
 * transport. A 4xx is treated as a permanent failure and dead-lettered at once.
 */
#[AsHandler(WebhookMessage::class)]
final readonly class WebhookHandler
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private SignerRegistry $signers,
        private SecretResolverInterface $secrets,
        private DeliveryStoreInterface $deliveries,
        private RetryPolicy $retryPolicy = new RetryPolicy(),
    ) {}

    public function __invoke(WebhookMessage $message): void
    {
        $delivery = $this->deliveries->findById($message->deliveryId);
        if (!$delivery instanceof Delivery) {
            // The delivery row vanished — nothing actionable; do not retry.
            throw new UnrecoverableMessageHandlingException(
                \sprintf('Webhook delivery "%s" is no longer in the store.', $message->deliveryId),
            );
        }

        $attempt = $delivery->attempts + 1;
        $now = time();

        $request = $this->buildRequest($message, $now);

        try {
            $status = $this->httpClient->sendRequest($request)->getStatusCode();
        } catch (ClientExceptionInterface $clientException) {
            $this->onTransientFailure($delivery, $attempt, $now, 'network: ' . $clientException->getMessage());
        }

        if ($status >= 200 && $status < 300) {
            $this->markDelivered($delivery, $attempt, $now, (string) $status);

            return;
        }

        if ($status >= 500) {
            $this->onTransientFailure($delivery, $attempt, $now, 'HTTP ' . $status);
        }

        // 4xx — the subscriber rejected the payload; retrying will not help.
        $this->deadLetter($delivery, $attempt, $now, 'HTTP ' . $status);
    }

    private function buildRequest(WebhookMessage $message, int $now): RequestInterface
    {
        $signer = $this->signers->get($message->signerName);
        $secret = $this->secrets->resolve($message->secretName);
        $signature = $signer->sign($message->payload, $secret);

        return $this->requestFactory->createRequest('POST', $message->subscriberUrl)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Signature', $signature)
            ->withHeader('X-Timestamp', (string) $now)
            ->withHeader('X-Event-Id', $message->deliveryId)
            ->withHeader('X-Delivery-Id', $message->deliveryId)
            ->withBody($this->streamFactory->createStream($message->payload));
    }

    private function markDelivered(Delivery $delivery, int $attempt, int $now, string $response): void
    {
        $this->deliveries->update(
            $delivery
                ->withStatus(DeliveryStatus::Delivered)
                ->withAttempts($attempt)
                ->withLastAttemptAt($now)
                ->withNextAttemptAt(null)
                ->withLastResponse($response),
        );
    }

    private function onTransientFailure(Delivery $delivery, int $attempt, int $now, string $response): never
    {
        if ($attempt >= $this->retryPolicy->maxAttempts) {
            $this->deadLetter($delivery, $attempt, $now, $response);
        }

        $this->deliveries->update(
            $delivery
                ->withStatus(DeliveryStatus::Failed)
                ->withAttempts($attempt)
                ->withLastAttemptAt($now)
                ->withNextAttemptAt($now + $this->retryPolicy->delayFor($attempt))
                ->withLastResponse($response),
        );

        throw new RecoverableMessageHandlingException(\sprintf(
            'Webhook delivery "%s" failed (attempt %d/%d): %s',
            $delivery->id,
            $attempt,
            $this->retryPolicy->maxAttempts,
            $response,
        ));
    }

    private function deadLetter(Delivery $delivery, int $attempt, int $now, string $response): never
    {
        $this->deliveries->update(
            $delivery
                ->withStatus(DeliveryStatus::DeadLettered)
                ->withAttempts($attempt)
                ->withLastAttemptAt($now)
                ->withNextAttemptAt(null)
                ->withLastResponse($response),
        );

        throw new UnrecoverableMessageHandlingException(\sprintf(
            'Webhook delivery "%s" dead-lettered after %d attempt(s): %s',
            $delivery->id,
            $attempt,
            $response,
        ));
    }
}

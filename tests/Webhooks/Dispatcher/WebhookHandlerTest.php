<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Dispatcher;

use Altair\Tests\Webhooks\Fixtures\StaticSecretResolver;
use Altair\Webhooks\Contracts\DeliveryStoreInterface;
use Altair\Webhooks\Dispatcher\RetryPolicy;
use Altair\Webhooks\Dispatcher\WebhookHandler;
use Altair\Webhooks\Dispatcher\WebhookMessage;
use Altair\Webhooks\Signing\HmacSha256Signer;
use Altair\Webhooks\Signing\SignerRegistry;
use Altair\Webhooks\Storage\Delivery;
use Altair\Webhooks\Storage\DeliveryStatus;
use Altair\Webhooks\Storage\InMemoryDeliveryStore;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[CoversClass(WebhookHandler::class)]
final class WebhookHandlerTest extends TestCase
{
    private const string SECRET = 'whsec_test';

    private const string PAYLOAD = '{"id":"order_1"}';

    public function testSuccessfulPostMarksDelivered(): void
    {
        $store = new InMemoryDeliveryStore();
        $store->record($this->pendingDelivery());

        $client = FakeHttpClient::returning(200);

        ($this->handler($client, $store))(($this->message()));

        $delivery = $store->findById('dlv_1');
        self::assertSame(DeliveryStatus::Delivered, $delivery?->status);
        self::assertSame(1, $delivery->attempts);
        self::assertSame('200', $delivery->lastResponse);
        self::assertNull($delivery->nextAttemptAt);
    }

    public function testTransient5xxBelowThresholdThrowsRecoverableAndMarksFailed(): void
    {
        $store = new InMemoryDeliveryStore();
        $store->record($this->pendingDelivery());

        $handler = $this->handler(FakeHttpClient::returning(503), $store, new RetryPolicy(maxAttempts: 3));

        try {
            $handler($this->message());
            self::fail('expected RecoverableMessageHandlingException');
        } catch (RecoverableMessageHandlingException) {
            // expected — Messenger will redeliver
        }

        $delivery = $store->findById('dlv_1');
        self::assertSame(DeliveryStatus::Failed, $delivery?->status);
        self::assertSame(1, $delivery->attempts);
        self::assertNotNull($delivery->nextAttemptAt);
    }

    public function testTransientFailureAtThresholdDeadLetters(): void
    {
        $store = new InMemoryDeliveryStore();
        // Already attempted twice; with maxAttempts=3 the next failure dead-letters.
        $store->record($this->pendingDelivery()->withAttempts(2));

        $handler = $this->handler(FakeHttpClient::returning(500), $store, new RetryPolicy(maxAttempts: 3));

        try {
            $handler($this->message());
            self::fail('expected UnrecoverableMessageHandlingException');
        } catch (UnrecoverableMessageHandlingException) {
            // expected — routed to the failure transport
        }

        $delivery = $store->findById('dlv_1');
        self::assertSame(DeliveryStatus::DeadLettered, $delivery?->status);
        self::assertSame(3, $delivery->attempts);
    }

    public function testNetworkFailureIsTreatedAsTransient(): void
    {
        $store = new InMemoryDeliveryStore();
        $store->record($this->pendingDelivery());

        $handler = $this->handler(FakeHttpClient::networkError(), $store, new RetryPolicy(maxAttempts: 3));

        $this->expectException(RecoverableMessageHandlingException::class);

        try {
            $handler($this->message());
        } finally {
            self::assertSame(DeliveryStatus::Failed, $store->findById('dlv_1')?->status);
        }
    }

    public function testClientErrorDeadLettersImmediately(): void
    {
        $store = new InMemoryDeliveryStore();
        $store->record($this->pendingDelivery());

        $handler = $this->handler(FakeHttpClient::returning(400), $store);

        try {
            $handler($this->message());
            self::fail('expected UnrecoverableMessageHandlingException');
        } catch (UnrecoverableMessageHandlingException) {
            // expected
        }

        self::assertSame(DeliveryStatus::DeadLettered, $store->findById('dlv_1')?->status);
    }

    public function testMissingDeliveryThrowsUnrecoverable(): void
    {
        $handler = $this->handler(FakeHttpClient::returning(200), new InMemoryDeliveryStore());

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $handler($this->message());
    }

    public function testRequestIsSignedWithExpectedHeaders(): void
    {
        $store = new InMemoryDeliveryStore();
        $store->record($this->pendingDelivery());

        $client = FakeHttpClient::returning(200);

        ($this->handler($client, $store))($this->message());

        $request = $client->lastRequest;
        self::assertNotNull($request);
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            (new HmacSha256Signer())->sign(self::PAYLOAD, self::SECRET),
            $request->getHeaderLine('X-Signature'),
        );
        self::assertSame('dlv_1', $request->getHeaderLine('X-Delivery-Id'));
        self::assertSame('dlv_1', $request->getHeaderLine('X-Event-Id'));
        self::assertNotSame('', $request->getHeaderLine('X-Timestamp'));
        self::assertSame(self::PAYLOAD, (string) $request->getBody());
    }

    private function handler(
        FakeHttpClient $client,
        DeliveryStoreInterface $store,
        RetryPolicy $policy = new RetryPolicy(),
    ): WebhookHandler {
        return new WebhookHandler(
            httpClient: $client,
            requestFactory: new RequestFactory(),
            streamFactory: new StreamFactory(),
            signers: SignerRegistry::default(),
            secrets: new StaticSecretResolver(self::SECRET),
            deliveries: $store,
            retryPolicy: $policy,
        );
    }

    private function pendingDelivery(): Delivery
    {
        return Delivery::create(
            id: 'dlv_1',
            eventName: 'order.created',
            subscriberUrl: 'https://example.test/hook',
            payload: self::PAYLOAD,
            secretName: 'partner-x',
            signerName: 'hmac-sha256',
            createdAt: 1_700_000_000,
        );
    }

    private function message(): WebhookMessage
    {
        return new WebhookMessage(
            deliveryId: 'dlv_1',
            eventName: 'order.created',
            payload: self::PAYLOAD,
            subscriberUrl: 'https://example.test/hook',
            secretName: 'partner-x',
            signerName: 'hmac-sha256',
        );
    }
}

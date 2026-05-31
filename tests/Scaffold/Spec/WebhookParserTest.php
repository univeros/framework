<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Spec;

use Altair\Scaffold\Spec\Ast\WebhookSpec;
use Altair\Scaffold\Spec\Parser;
use Altair\Scaffold\Spec\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookSpec::class)]
#[CoversClass(Parser::class)]
#[CoversClass(Validator::class)]
final class WebhookParserTest extends TestCase
{
    public function testAbsentWebhookBlockIsNull(): void
    {
        $spec = (new Parser())->parseString($this->spec(''));

        self::assertNull($spec->webhook);
    }

    public function testParsesInboundBlock(): void
    {
        $spec = (new Parser())->parseString($this->spec(<<<'YAML'
            webhook:
              direction: in
              signing: hmac-sha256
              secret_name: stripe
              header: X-Stripe-Signature
              dedupe_ttl: 2h
              timestamp_window: 10m
            YAML));

        $webhook = $spec->webhook;
        self::assertInstanceOf(WebhookSpec::class, $webhook);
        self::assertTrue($webhook->isInbound());
        self::assertSame('hmac-sha256', $webhook->signing);
        self::assertSame('stripe', $webhook->secretName);
        self::assertSame('X-Stripe-Signature', $webhook->signatureHeader);
        self::assertSame('2h', $webhook->dedupeTtl);
        self::assertSame('10m', $webhook->timestampWindow);
    }

    public function testParsesOutboundBlockWithRetry(): void
    {
        $spec = (new Parser())->parseString($this->spec(<<<'YAML'
            webhook:
              direction: out
              signing: hmac-sha512
              retry:
                max_attempts: 7
                backoff: linear
                base_delay: 15s
              dead_letter: webhook.dlq
            YAML));

        $webhook = $spec->webhook;
        self::assertInstanceOf(WebhookSpec::class, $webhook);
        self::assertTrue($webhook->isOutbound());
        self::assertSame('hmac-sha512', $webhook->signing);
        self::assertSame(7, $webhook->retryMaxAttempts);
        self::assertSame('linear', $webhook->retryBackoff);
        self::assertSame('15s', $webhook->retryBaseDelay);
        self::assertSame('webhook.dlq', $webhook->deadLetterTransport);
    }

    public function testInboundDefaultsApply(): void
    {
        $spec = (new Parser())->parseString($this->spec(<<<'YAML'
            webhook:
              direction: in
              signing: hmac-sha256
              secret_name: github
            YAML));

        $webhook = $spec->webhook;
        self::assertInstanceOf(WebhookSpec::class, $webhook);
        self::assertSame('X-Signature', $webhook->signatureHeader);
        self::assertSame('1h', $webhook->dedupeTtl);
        self::assertSame('5m', $webhook->timestampWindow);
    }

    public function testValidatorAcceptsValidInbound(): void
    {
        $spec = (new Parser())->parseString($this->spec(<<<'YAML'
            webhook:
              direction: in
              signing: hmac-sha256
              secret_name: stripe
            YAML));

        self::assertSame([], (new Validator())->collectErrors($spec));
    }

    public function testValidatorRejectsUnknownDirection(): void
    {
        $errors = $this->errorsFor(<<<'YAML'
            webhook:
              direction: sideways
              signing: hmac-sha256
              secret_name: stripe
            YAML);

        self::assertNotEmpty(array_filter($errors, static fn (string $e): bool => str_contains($e, 'webhook.direction')));
    }

    public function testValidatorRejectsUnknownSigner(): void
    {
        $errors = $this->errorsFor(<<<'YAML'
            webhook:
              direction: in
              signing: rot13
              secret_name: stripe
            YAML);

        self::assertNotEmpty(array_filter($errors, static fn (string $e): bool => str_contains($e, 'webhook.signing')));
    }

    public function testValidatorRequiresSecretNameForInbound(): void
    {
        $errors = $this->errorsFor(<<<'YAML'
            webhook:
              direction: in
              signing: hmac-sha256
            YAML);

        self::assertNotEmpty(array_filter($errors, static fn (string $e): bool => str_contains($e, 'secret_name')));
    }

    public function testValidatorRejectsMalformedDuration(): void
    {
        $errors = $this->errorsFor(<<<'YAML'
            webhook:
              direction: in
              signing: hmac-sha256
              secret_name: stripe
              dedupe_ttl: forever
            YAML);

        self::assertNotEmpty(array_filter($errors, static fn (string $e): bool => str_contains($e, 'dedupe_ttl')));
    }

    public function testValidatorRejectsBadBackoff(): void
    {
        $errors = $this->errorsFor(<<<'YAML'
            webhook:
              direction: out
              signing: hmac-sha256
              retry:
                backoff: fibonacci
            YAML);

        self::assertNotEmpty(array_filter($errors, static fn (string $e): bool => str_contains($e, 'backoff')));
    }

    /**
     * @return list<string>
     */
    private function errorsFor(string $webhookBlock): array
    {
        return (new Validator())->collectErrors((new Parser())->parseString($this->spec($webhookBlock)));
    }

    private function spec(string $webhookBlock): string
    {
        return <<<YAML
            endpoint:
              method: POST
              path: /webhooks/stripe
            domain:
              class: App\\Webhooks\\HandleStripeEvent
            {$webhookBlock}
            YAML;
    }
}

<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Emitter;

use Altair\Scaffold\Spec\Ast\Spec;
use Altair\Scaffold\Emitter\EmittedFileKind;
use Altair\Scaffold\Emitter\WebhookDispatcherBindingEmitter;
use Altair\Scaffold\Spec\Parser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookDispatcherBindingEmitter::class)]
final class WebhookDispatcherBindingEmitterTest extends TestCase
{
    public function testEmitsConfiguredDispatcherBinding(): void
    {
        $file = (new WebhookDispatcherBindingEmitter())->emit($this->outboundSpec());

        self::assertSame(EmittedFileKind::WebhookDispatcher, $file->kind);
        self::assertSame('app/Webhooks/PublishPostWebhookDispatcher.php', $file->relativePath);
        self::assertStringContainsString('final readonly class PublishPostWebhookDispatcher', $file->contents);
        self::assertStringContainsString("public const string SIGNING = 'hmac-sha256';", $file->contents);
        self::assertStringContainsString("public const ?string DEAD_LETTER_TRANSPORT = 'webhook.dlq';", $file->contents);
        self::assertStringContainsString('use Altair\\Webhooks\\Dispatcher\\WebhookDispatcher;', $file->contents);
    }

    public function testRetryPolicyReflectsSpecAndConvertsBaseDelay(): void
    {
        $file = (new WebhookDispatcherBindingEmitter())->emit($this->outboundSpec());

        // base_delay: 2m -> 120 seconds, max_attempts: 4, backoff: linear
        self::assertStringContainsString(
            "new RetryPolicy(maxAttempts: 4, backoff: 'linear', baseDelaySeconds: 120)",
            $file->contents,
        );
    }

    public function testDeterministicOutput(): void
    {
        $first = (new WebhookDispatcherBindingEmitter())->emit($this->outboundSpec());
        $second = (new WebhookDispatcherBindingEmitter())->emit($this->outboundSpec());

        self::assertSame($first->contents, $second->contents);
    }

    private function outboundSpec(): Spec
    {
        return (new Parser())->parseString(<<<'YAML'
            endpoint:
              method: POST
              path: /posts
            domain:
              class: App\Posts\PublishPost
            webhook:
              direction: out
              signing: hmac-sha256
              retry:
                max_attempts: 4
                backoff: linear
                base_delay: 2m
              dead_letter: webhook.dlq
            YAML);
    }
}

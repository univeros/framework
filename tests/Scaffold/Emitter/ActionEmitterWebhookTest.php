<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Emitter;

use Altair\Scaffold\Emitter\ActionEmitter;
use Altair\Scaffold\Spec\Parser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActionEmitter::class)]
final class ActionEmitterWebhookTest extends TestCase
{
    public function testInboundSpecEmitsWebhookAccessor(): void
    {
        $contents = $this->emit(<<<'YAML'
            webhook:
              direction: in
              signing: hmac-sha256
              secret_name: stripe
              dedupe_ttl: 2h
            YAML);

        self::assertStringContainsString('public static function webhook(): array', $contents);
        self::assertStringContainsString("'direction' => 'in'", $contents);
        self::assertStringContainsString("'signing' => 'hmac-sha256'", $contents);
        self::assertStringContainsString("'secret_name' => 'stripe'", $contents);
        self::assertStringContainsString("'dedupe_ttl' => '2h'", $contents);
    }

    public function testOutboundSpecDoesNotEmitWebhookAccessor(): void
    {
        $contents = $this->emit(<<<'YAML'
            webhook:
              direction: out
              signing: hmac-sha256
            YAML);

        self::assertStringNotContainsString('public static function webhook()', $contents);
    }

    public function testAbsentWebhookLeavesActionByteIdentical(): void
    {
        $withoutBlock = $this->emit('');

        self::assertStringNotContainsString('public static function webhook', $withoutBlock);
    }

    public function testDeterministicOutput(): void
    {
        $block = <<<'YAML'
            webhook:
              direction: in
              signing: hmac-sha256
              secret_name: stripe
            YAML;

        self::assertSame($this->emit($block), $this->emit($block));
    }

    private function emit(string $webhookBlock): string
    {
        $spec = (new Parser())->parseString(<<<YAML
            endpoint:
              method: POST
              path: /webhooks/stripe
            domain:
              class: App\\Webhooks\\HandleStripeEvent
            {$webhookBlock}
            YAML);

        return (new ActionEmitter())->emit($spec)->contents;
    }
}

<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Signing;

use Altair\Webhooks\Exception\WebhookException;
use Altair\Webhooks\Signing\EnvSecretResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnvSecretResolver::class)]
final class EnvSecretResolverTest extends TestCase
{
    /** @var list<string> */
    private array $touchedKeys = [];

    protected function tearDown(): void
    {
        foreach ($this->touchedKeys as $key) {
            putenv($key);
        }

        $this->touchedKeys = [];
    }

    public function testResolvesSecretFromEnv(): void
    {
        $this->setEnv('WEBHOOK_SECRET_STRIPE', 'whsec_abc');

        self::assertSame('whsec_abc', (new EnvSecretResolver())->resolve('stripe'));
    }

    public function testNormalisesNonAlphanumericsInName(): void
    {
        $this->setEnv('WEBHOOK_SECRET_PARTNER_X', 'sk_xyz');

        self::assertSame('sk_xyz', (new EnvSecretResolver())->resolve('partner-x'));
    }

    public function testThrowsWhenSecretMissing(): void
    {
        $this->expectException(WebhookException::class);
        $this->expectExceptionMessage('Webhook secret "ghost" is not configured.');

        (new EnvSecretResolver())->resolve('ghost');
    }

    public function testHonoursCustomPrefix(): void
    {
        $this->setEnv('HOOK_GITHUB', 'gh_secret');

        self::assertSame('gh_secret', (new EnvSecretResolver('HOOK_'))->resolve('github'));
    }

    private function setEnv(string $key, string $value): void
    {
        putenv($key . '=' . $value);
        $this->touchedKeys[] = $key;
    }
}

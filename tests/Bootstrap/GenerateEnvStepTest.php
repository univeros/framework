<?php

declare(strict_types=1);

namespace Altair\Tests\Bootstrap;

use Altair\Bootstrap\Profile\MinimalPreset;
use Altair\Bootstrap\Profile\StandardPreset;
use Altair\Bootstrap\Step\GenerateEnvStep;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GenerateEnvStep::class)]
final class GenerateEnvStepTest extends TestCase
{
    private string $dir;

    #[Override]
    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/altair-env-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0o755, true);
        file_put_contents($this->dir . '/.env.example', "APP_ENV=dev\nAPP_KEY=changeme\nMESSENGER_TRANSPORT_DSN=sync://\n");
    }

    #[Override]
    protected function tearDown(): void
    {
        @unlink($this->dir . '/.env.example');
        @unlink($this->dir . '/.env');
        @rmdir($this->dir);
    }

    public function testStandardPresetWritesRedisTransport(): void
    {
        (new GenerateEnvStep())->run($this->dir, new StandardPreset());

        $env = (string) file_get_contents($this->dir . '/.env');
        self::assertStringContainsString('MESSENGER_TRANSPORT_DSN=redis://localhost:6379/messages', $env);
        // Secrets stay placeholders, never auto-generated.
        self::assertStringContainsString('APP_KEY=changeme', $env);
    }

    public function testMinimalPresetKeepsSyncTransport(): void
    {
        (new GenerateEnvStep())->run($this->dir, new MinimalPreset());

        self::assertStringContainsString('MESSENGER_TRANSPORT_DSN=sync://', (string) file_get_contents($this->dir . '/.env'));
    }
}

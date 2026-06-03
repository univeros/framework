<?php

declare(strict_types=1);

namespace Altair\Tests\Logging;

use Altair\Configuration\Support\Env;
use Altair\Logging\Configuration\LoggingSettings;
use PHPUnit\Framework\TestCase;

final class LoggingSettingsTest extends TestCase
{
    private const array KEYS = ['LOG_CHANNEL', 'LOG_LEVEL', 'LOG_PATH', 'LOG_FORMAT'];

    /** @var array<string, ?string> */
    private array $original = [];

    protected function setUp(): void
    {
        foreach (self::KEYS as $key) {
            $this->original[$key] = \array_key_exists($key, $_ENV) && \is_string($_ENV[$key]) ? $_ENV[$key] : null;
            unset($_ENV[$key]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->original as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
    }

    public function testDefaults(): void
    {
        $settings = LoggingSettings::fromEnv(new Env());

        $this->assertSame('app', $settings->channel);
        $this->assertSame('debug', $settings->level);
        $this->assertSame('php://stderr', $settings->path);
        $this->assertSame('json', $settings->format);
        $this->assertTrue($settings->isJson());
    }

    public function testReadsExplicitEnvironment(): void
    {
        $_ENV['LOG_CHANNEL'] = 'orders';
        $_ENV['LOG_LEVEL'] = 'warning';
        $_ENV['LOG_PATH'] = '/var/log/app.log';
        $_ENV['LOG_FORMAT'] = 'line';

        $settings = LoggingSettings::fromEnv(new Env());

        $this->assertSame('orders', $settings->channel);
        $this->assertSame('warning', $settings->level);
        $this->assertSame('/var/log/app.log', $settings->path);
        $this->assertSame('line', $settings->format);
        $this->assertFalse($settings->isJson());
    }

    public function testBlankValuesFallBackToDefaults(): void
    {
        $_ENV['LOG_CHANNEL'] = '   ';
        $_ENV['LOG_PATH'] = '';

        $settings = LoggingSettings::fromEnv(new Env());

        $this->assertSame('app', $settings->channel);
        $this->assertSame('php://stderr', $settings->path);
    }

    public function testUnknownFormatResolvesToJson(): void
    {
        $_ENV['LOG_FORMAT'] = 'xml';

        $this->assertSame('json', LoggingSettings::fromEnv(new Env())->format);
    }

    public function testFormatIsCaseInsensitive(): void
    {
        $_ENV['LOG_FORMAT'] = 'LINE';

        $this->assertSame('line', LoggingSettings::fromEnv(new Env())->format);
    }
}

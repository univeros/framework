<?php

declare(strict_types=1);

namespace Altair\Tests\Logging;

use Altair\Container\Container;
use Altair\Logging\Configuration\LoggingConfiguration;
use Altair\Logging\Configuration\LoggingSettings;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class LoggingConfigurationTest extends TestCase
{
    /** @var list<string> */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        foreach (['LOG_CHANNEL', 'LOG_LEVEL', 'LOG_PATH', 'LOG_FORMAT'] as $key) {
            unset($_ENV[$key]);
        }
    }

    public function testJsonFormatWritesNewlineDelimitedJson(): void
    {
        $file = $this->tmpFile();

        $logger = LoggingConfiguration::createLogger(new LoggingSettings('test', 'debug', $file, 'json'));
        $logger->error('kaboom', ['user' => 7]);
        $logger->close();

        $decoded = json_decode(trim((string) file_get_contents($file)), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('kaboom', $decoded['message']);
        $this->assertSame('ERROR', $decoded['level_name']);
        $this->assertSame(['user' => 7], $decoded['context']);
        $this->assertSame('test', $decoded['channel']);
    }

    public function testLevelThresholdFiltersLowerLevels(): void
    {
        $file = $this->tmpFile();

        $logger = LoggingConfiguration::createLogger(new LoggingSettings('test', 'error', $file, 'json'));
        $logger->info('quietly ignored');
        $logger->warning('also ignored');
        $logger->error('kept');
        $logger->close();

        $contents = (string) file_get_contents($file);
        $this->assertStringContainsString('kept', $contents);
        $this->assertStringNotContainsString('ignored', $contents);
    }

    public function testLineFormatIsHumanReadableNotJson(): void
    {
        $file = $this->tmpFile();

        $logger = LoggingConfiguration::createLogger(new LoggingSettings('test', 'debug', $file, 'line'));
        $logger->warning('hello');
        $logger->close();

        $line = trim((string) file_get_contents($file));
        $this->assertStringContainsString('test.WARNING', $line);
        $this->assertNull(json_decode($line, true));
    }

    public function testUnknownLevelDefaultsToDebug(): void
    {
        $file = $this->tmpFile();

        $logger = LoggingConfiguration::createLogger(new LoggingSettings('test', 'not-a-level', $file, 'json'));
        $logger->debug('debug still flows');
        $logger->close();

        $this->assertStringContainsString('debug still flows', (string) file_get_contents($file));
    }

    public function testApplyBindsSharedPsrLoggerBackedByMonolog(): void
    {
        $file = $this->tmpFile();
        $_ENV['LOG_PATH'] = $file;
        $_ENV['LOG_CHANNEL'] = 'wired';

        $container = new Container();
        (new LoggingConfiguration())->apply($container);

        $logger = $container->get(LoggerInterface::class);
        $this->assertInstanceOf(Logger::class, $logger);
        $this->assertSame($logger, $container->get(LoggerInterface::class), 'logger should be a shared singleton');

        $logger->error('through the container');
        $logger->close();

        $contents = (string) file_get_contents($file);
        $this->assertStringContainsString('through the container', $contents);
        $this->assertStringContainsString('"channel":"wired"', $contents);
    }

    private function tmpFile(): string
    {
        $file = sys_get_temp_dir() . '/altair-logging-test-' . uniqid('', true) . '.log';
        $this->tmpFiles[] = $file;

        return $file;
    }
}

<?php

declare(strict_types=1);

namespace Altair\Tests\Configuration;

use Altair\Configuration\Collection\ConfigurationCollection;
use Altair\Configuration\EnvironmentConfiguration;
use Altair\Configuration\Exception\InvalidArgumentException;
use Altair\Configuration\Support\Env;
use Altair\Container\Container;
use Altair\Container\Definition;
use Dotenv\Exception\InvalidFileException;
use PHPUnit\Framework\TestCase;

class EnvironmentConfigurationTest extends TestCase
{
    public function testMethodApplyShouldHaveContainerWithSharedClassEnv(): void
    {
        $container = $this->prepareContainer(__DIR__ . '/fixtures/good.env');

        $this->assertTrue($container->getShares()->hasKey(strtolower(Env::class)));
    }

    public function testMethodApplyPreparesSharedClassEnvWithLoadedEnvironmentFileValues(): void
    {
        $container = $this->prepareContainer(__DIR__ . '/fixtures/good.env');

        $env = $container->make(Env::class);

        $this->assertSame('bar', $env->get('FOO'));
        $this->assertSame('baz', $env->get('BAR'));
        $this->assertSame('Hello', $env->get('NVAR1'));
        $this->assertSame('Hello World!', $env->get('NVAR3'));
        $this->assertSame('This is default', $env->get('UNKWOWN', 'This is default'));
    }

    public function testInvalidArgumentExceptionOnWrongFilePath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid environment file path/');

        $this->prepareContainer('unknown');
    }

    public function testDotEnvInvalidFileExceptionOnWrongEnvFileFormat(): void
    {
        $this->expectException(InvalidFileException::class);

        $container = $this->prepareContainer(__DIR__ . '/fixtures/wrong.env');
        $container->make(Env::class);
    }

    private function prepareContainer(string $filePath): Container
    {
        $container = new Container();
        $container->define(
            EnvironmentConfiguration::class,
            new Definition([':filePath' => $filePath]),
        );
        $configuration = new ConfigurationCollection([EnvironmentConfiguration::class]);
        $configuration->apply($container);

        return $container;
    }
}

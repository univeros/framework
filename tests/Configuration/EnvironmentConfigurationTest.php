<?php
namespace Altair\Tests\Configuration;

use Altair\Configuration\Collection\ConfigurationCollection;
use Altair\Configuration\EnvironmentConfiguration;
use Altair\Configuration\Support\Env;
use Altair\Container\Container;
use Altair\Container\Definition;
use PHPUnit\Framework\TestCase;

class EnvironmentConfigurationTest extends TestCase
{
    public function testMethodApplyShouldHaveContainerWithSharedClassEnv()
    {
        $container = new Container();
        $container->define(
            EnvironmentConfiguration::class,
            new Definition([':filePath' => __DIR__ . '/fixtures/good.env'])
        );
        $configuration = new ConfigurationCollection(
            [
                EnvironmentConfiguration::class
            ]
        );
        $configuration->apply($container);
        $this->assertTrue($container->getShares()->hasKey(strtolower(Env::class)));
    }

    public function testMethodApplyPreparesSharedClassEnvWithLoadedEnvironmentFileValues()
    {
        $container = new Container();
        $container->define(
            EnvironmentConfiguration::class,
            new Definition([':filePath' => __DIR__ . '/fixtures/good.env'])
        );
        $configuration = new ConfigurationCollection(
            [
                EnvironmentConfiguration::class
            ]
        );
        $configuration->apply($container);

        $env = $container->make(Env::class);

        $this->assertEquals('bar', $env->get('FOO'));
        $this->assertEquals('baz', $env->get('BAR'));
        $this->assertEquals('Hello', $env->get('NVAR1'));
        $this->assertEquals('Hello World!', $env->get('NVAR3'));
        $this->assertEquals('This is default', $env->get('UNKWOWN', 'This is default'));
    }

    /**
     * @expectedException \Altair\Configuration\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp /Invalid environment file path/
     */
    public function testInvalidArgumentExceptionOnWrongFilePath()
    {
        $container = new Container();
        $container->define(EnvironmentConfiguration::class, new Definition([':filePath' => 'unknown']));
        $configuration = new ConfigurationCollection(
            [
                EnvironmentConfiguration::class
            ]
        );
        $configuration->apply($container);

        $env = $container->make(Env::class);
    }

    /**
     * @expectedException \Dotenv\Exception\InvalidFileException
     * @expectedExceptionMessageRegExp /Dotenv values containing spaces must be surrounded by/
     */
    public function testDotEnvInvalidFileExceptionOnWrongEnvFileFormat()
    {
        $container = new Container();
        $container->define(
            EnvironmentConfiguration::class,
            new Definition([':filePath' => __DIR__ . '/fixtures/wrong.env'])
        );
        $configuration = new ConfigurationCollection(
            [
                EnvironmentConfiguration::class
            ]
        );
        $configuration->apply($container);

        $env = $container->make(Env::class);
    }
}

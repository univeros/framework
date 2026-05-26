<?php
namespace Altair\Tests\Configuration;

use Altair\Configuration\Collection\ConfigurationCollection;
use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use PHPUnit\Framework\TestCase;

class ConfigurationCollectionTest extends TestCase
{
    public function testCollectionApplyWithClassNameString()
    {
        $configuration = $this->createMock(ConfigurationInterface::class);

        $container = $this->createMock(Container::class);

        $container
            ->expects($this->once())
            ->method('make')
            ->with(get_class($configuration))
            ->willReturn($configuration);

        $configuration
            ->expects($this->once())
            ->method('apply')
            ->with($container);

        $collection = new ConfigurationCollection([
            get_class($configuration)
        ]);

        $collection->apply($container);
    }

    public function testCollectionApplyWithObjectInstance()
    {
        $configuration = $this->createMock(ConfigurationInterface::class);

        $container = $this->createMock(Container::class);

        $configuration
            ->expects($this->once())
            ->method('apply')
            ->with($container);

        $collection = new ConfigurationCollection([
            $configuration
        ]);

        $collection->apply($container);
    }

    public function testInvalidClass()
    {
        $this->expectException(\Altair\Configuration\Exception\InvalidConfigurationException::class);

        $container = $this->createMock(Container::class);
        $configuration = new ConfigurationCollection([
            '\stdClass'
        ]);

        $configuration->apply($container);
    }
}

<?php
namespace Altair\Tests\Configuration;

use Altair\Configuration\Exception\InvalidConfigurationException;
use Altair\Configuration\Collection\ConfigurationCollection;
use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use PHPUnit\Framework\TestCase;

class ConfigurationCollectionTest extends TestCase
{
    public function testCollectionApplyWithClassNameString(): void
    {
        $configuration = $this->createMock(ConfigurationInterface::class);

        $container = $this->createMock(Container::class);

        $container
            ->expects($this->once())
            ->method('make')
            ->with($configuration::class)
            ->willReturn($configuration);

        $configuration
            ->expects($this->once())
            ->method('apply')
            ->with($container);

        $collection = new ConfigurationCollection([
            $configuration::class
        ]);

        $collection->apply($container);
    }

    public function testCollectionApplyWithObjectInstance(): void
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

    public function testInvalidClass(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $container = $this->createMock(Container::class);
        $configuration = new ConfigurationCollection([
            '\stdClass'
        ]);

        $configuration->apply($container);
    }
}

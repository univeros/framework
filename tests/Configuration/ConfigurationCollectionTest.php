<?php

declare(strict_types=1);

namespace Altair\Tests\Configuration;

use Altair\Configuration\Collection\ConfigurationCollection;
use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Exception\InvalidConfigurationException;
use Altair\Container\Container;
use Override;
use PHPUnit\Framework\TestCase;

class ConfigurationCollectionTest extends TestCase
{
    public function testCollectionApplyWithClassNameString(): void
    {
        $container = new Container();
        $spy = new SpyConfiguration();
        // Register the instance so the collection's make() returns this very spy.
        $container->instance(SpyConfiguration::class, $spy);

        $collection = new ConfigurationCollection([
            SpyConfiguration::class,
        ]);

        $collection->apply($container);

        // Resolving the configuration by class name and invoking apply() records
        // the container it was handed.
        self::assertSame($container, $spy->appliedTo);
    }

    public function testCollectionApplyWithObjectInstance(): void
    {
        $container = new Container();
        $configuration = new SpyConfiguration();

        $collection = new ConfigurationCollection([
            $configuration,
        ]);

        $collection->apply($container);

        self::assertSame($container, $configuration->appliedTo);
    }

    public function testInvalidClass(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $container = new Container();
        $configuration = new ConfigurationCollection([
            '\stdClass',
        ]);

        $configuration->apply($container);
    }
}

final class SpyConfiguration implements ConfigurationInterface
{
    public ?Container $appliedTo = null;

    #[Override]
    public function apply(Container $container): void
    {
        $this->appliedTo = $container;
    }
}

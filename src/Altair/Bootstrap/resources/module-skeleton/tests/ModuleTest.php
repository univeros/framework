<?php

declare(strict_types=1);

namespace VendorModule\Tests;

use Altair\Container\Container;
use Altair\Module\Migration\MigrationSource;
use PHPUnit\Framework\TestCase;
use VendorModule\Domain\SampleService;
use VendorModule\Http\Actions\SampleAction;
use VendorModule\Module;

final class ModuleTest extends TestCase
{
    public function testContributesItsRoute(): void
    {
        self::assertSame([['GET', '/sample', SampleAction::class]], (new Module())->routes());
    }

    public function testApplyBindsTheService(): void
    {
        $container = new Container();
        (new Module())->apply($container);

        self::assertInstanceOf(SampleService::class, $container->get(SampleService::class));
    }

    public function testEntityDirectoriesExist(): void
    {
        foreach ((new Module())->entityDirectories() as $directory) {
            self::assertDirectoryExists($directory);
        }
    }

    public function testMigrationDirectoriesExist(): void
    {
        foreach ((new Module())->migrationDirectories() as $source) {
            self::assertInstanceOf(MigrationSource::class, $source);
            self::assertDirectoryExists($source->directory);
        }
    }
}

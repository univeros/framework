<?php

declare(strict_types=1);

namespace Altair\Tests\Module;

use Altair\Container\Container;
use Altair\Module\Contracts\ModuleInterface;
use Altair\Module\ModuleConfiguration;
use Override;
use PHPUnit\Framework\TestCase;

final class ModuleConfigurationTest extends TestCase
{
    public function testApplyTagsAndAppliesEachModule(): void
    {
        $container = new Container();
        $module = new SpyModule();

        (new ModuleConfiguration([$module]))->apply($container);

        // The module's own apply() ran against the same container.
        self::assertSame($container, $module->appliedTo);

        // The module is discoverable by the canonical module tag.
        $tagged = iterator_to_array($container->tagged(ModuleConfiguration::MODULE_TAG), false);
        self::assertSame([$module], $tagged);
    }

    public function testEmptyModuleListIsANoOp(): void
    {
        $container = new Container();

        (new ModuleConfiguration([]))->apply($container);

        self::assertSame([], iterator_to_array($container->tagged(ModuleConfiguration::MODULE_TAG), false));
    }

    public function testModuleExposesItsName(): void
    {
        self::assertSame('spy', (new SpyModule())->name());
    }
}

final class SpyModule implements ModuleInterface
{
    public ?Container $appliedTo = null;

    #[Override]
    public function name(): string
    {
        return 'spy';
    }

    #[Override]
    public function apply(Container $container): void
    {
        $this->appliedTo = $container;
    }
}

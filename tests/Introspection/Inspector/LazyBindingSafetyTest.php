<?php

declare(strict_types=1);

namespace Altair\Tests\Introspection\Inspector;

use Altair\Container\Container;
use Altair\Introspection\Inspector\ContainerInspector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Key acceptance criterion from #71: walking the Container with the
 * introspection inspector must NEVER trigger instantiation or fire
 * `prepare` hooks. That's what makes the inspector safe to run against
 * projects whose databases are down or whose `prepare` hooks have
 * external side effects.
 *
 * The test wires a Container where `make()` would explode (delegate
 * throws, prepare hook throws) and asserts inspection still completes.
 */
#[CoversClass(ContainerInspector::class)]
class LazyBindingSafetyTest extends TestCase
{
    public function testInspectionDoesNotInstantiateDelegates(): void
    {
        $container = new Container();

        $invoked = 0;
        $container->delegate(
            \stdClass::class,
            static function () use (&$invoked): \stdClass {
                $invoked++;
                throw new \RuntimeException(
                    'Inspector triggered a delegate — this proves the lazy-binding contract is broken.',
                );
            },
        );

        // Full inventory + targeted inspect must both stay quiet.
        $table = (new ContainerInspector($container))->inspectAll();
        $this->assertNotSame([], $table->rows);

        $detail = (new ContainerInspector($container))->inspectOne(\stdClass::class);
        $this->assertNotSame([], $detail->rows);

        $this->assertSame(0, $invoked, 'Delegate must NEVER fire during introspection.');
    }

    public function testInspectionDoesNotFirePrepareHooks(): void
    {
        $container = new Container();
        $container->share(new \stdClass());

        $invoked = 0;
        $container->prepare(\stdClass::class, static function () use (&$invoked): void {
            $invoked++;
            throw new \RuntimeException('Inspector triggered a prepare hook — lazy-binding contract violated.');
        });

        (new ContainerInspector($container))->inspectAll();
        (new ContainerInspector($container))->inspectOne(\stdClass::class);

        $this->assertSame(0, $invoked, 'Prepare hook must NEVER fire during introspection.');
    }
}

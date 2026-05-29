<?php

declare(strict_types=1);

namespace Altair\Tests\Tinker\Repl;

use Altair\Container\Container;
use Altair\Tinker\Repl\ContainerCaster;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(ContainerCaster::class)]
class ContainerCasterTest extends TestCase
{
    public function testCastSummarisesContainer(): void
    {
        $container = new Container();
        $container->share(new stdClass());

        $cast = ContainerCaster::cast($container);

        $this->assertSame(Container::class, $cast['class']);
        $this->assertGreaterThanOrEqual(1, $cast['realised singletons']);
        $this->assertStringContainsString('make(', $cast['tip']);
    }

    public function testEmptyContainerHasNoRealisedSingletons(): void
    {
        $cast = ContainerCaster::cast(new Container());

        $this->assertSame(0, $cast['realised singletons']);
    }
}

<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Resolver;

use Altair\Container\Container;
use Altair\Http\Resolver\ContainerResolver;
use PHPUnit\Framework\TestCase;
use stdClass;

class ContainerResolverTest extends TestCase
{
    public function testStringEntryIsResolvedViaContainer(): void
    {
        $resolver = new ContainerResolver(new Container());

        $instance = $resolver(stdClass::class);

        $this->assertInstanceOf(stdClass::class, $instance);
    }

    public function testObjectEntryIsReturnedAsIs(): void
    {
        $existing = new stdClass();
        $resolver = new ContainerResolver(new Container());

        $this->assertSame($existing, $resolver($existing));
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Examples;

use Altair\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * Runs the snippet from .altair/examples/container/define-and-resolve.md
 * end-to-end. Asserts that singleton() + get() returns the same instance
 * and that make() always returns a fresh one (the unusual-but-documented
 * shape of the framework's container).
 */
final class ContainerDefineAndResolveTest extends TestCase
{
    public function testSingletonReturnsTheSameInstanceViaGet(): void
    {
        $container = new Container();
        $container->singleton(ContainerExampleCounter::class, static fn(): ContainerExampleCounter
            => new ContainerExampleCounter());

        $first = $container->get(ContainerExampleCounter::class);
        $second = $container->get(ContainerExampleCounter::class);

        self::assertSame($first, $second);

        $first->increment();
        self::assertSame(1, $second->count, 'mutations survive because both refer to one instance');
    }

    public function testMakeReturnsFreshInstancesEvenForSharedBindings(): void
    {
        $container = new Container();
        $container->singleton(ContainerExampleCounter::class, static fn(): ContainerExampleCounter
            => new ContainerExampleCounter());

        self::assertNotSame(
            $container->make(ContainerExampleCounter::class),
            $container->make(ContainerExampleCounter::class),
        );
    }
}

/**
 * @internal
 */
final class ContainerExampleCounter
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }
}

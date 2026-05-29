<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Container;

use Altair\Container\Container;
use Altair\Container\Contracts\FactoryInterface;
use Altair\Container\Contracts\InvokerInterface;
use Altair\Container\Exception\AutowireException;
use Altair\Container\Exception\CircularDependencyException;
use Altair\Container\Exception\ContainerException;
use Altair\Container\Exception\NotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(Container::class)]
final class ContainerTest extends TestCase
{
    public function testAutowiresConcreteDependency(): void
    {
        $container = new Container();

        $object = $container->make(NeedsDependency::class);

        self::assertInstanceOf(Dependency::class, $object->dependency);
        self::assertSame('dep', $object->dependency->marker);
    }

    public function testInstantiatesClassWithoutConstructor(): void
    {
        self::assertTrue((new Container())->make(NoDeps::class)->built);
    }

    public function testAliasResolvesInterfaceToImplementation(): void
    {
        $container = new Container();
        $container->alias(LoggerInterface::class, FileLogger::class);

        self::assertInstanceOf(FileLogger::class, $container->get(LoggerInterface::class));
        self::assertSame('file', $container->make(NeedsLogger::class)->logger->channel());
    }

    public function testGetThrowsNotFoundForUnknownId(): void
    {
        $this->expectException(NotFoundException::class);

        (new Container())->get('no.such.service');
    }

    public function testThrowsAutowireExceptionWhenDependencyUnresolvable(): void
    {
        $this->expectException(AutowireException::class);

        (new Container())->make(NeedsLogger::class);
    }

    public function testSingletonReturnsSameInstance(): void
    {
        $container = new Container();
        $container->singleton(Dependency::class);

        self::assertSame($container->get(Dependency::class), $container->get(Dependency::class));
    }

    public function testMakeAlwaysReturnsFreshInstanceEvenWhenShared(): void
    {
        $container = new Container();
        $container->singleton(Dependency::class);

        self::assertNotSame($container->make(Dependency::class), $container->make(Dependency::class));
        self::assertSame($container->get(Dependency::class), $container->get(Dependency::class));
    }

    public function testFactoryClosureAutowiresItsParameters(): void
    {
        $container = new Container();
        $container->factory(NeedsDependency::class, static fn(Dependency $dep): NeedsDependency => new NeedsDependency($dep));

        self::assertSame('dep', $container->get(NeedsDependency::class)->dependency->marker);
    }

    public function testInstanceBindingReturnsTheSameObject(): void
    {
        $container = new Container();
        $instance = new Dependency();
        $container->instance(Dependency::class, $instance);

        self::assertSame($instance, $container->get(Dependency::class));
    }

    public function testValueBindingFeedsAutowireParamAttribute(): void
    {
        $container = new Container();
        $container->value('app.locale', 'en-GB');

        self::assertSame('en-GB', $container->make(AutowireParamConsumer::class)->locale);
    }

    public function testInjectAttributeResolvesSpecificImplementation(): void
    {
        self::assertSame('file', (new Container())->make(InjectConsumer::class)->logger->channel());
    }

    public function testAutowireServiceAttributeResolvesSpecificService(): void
    {
        self::assertSame('null', (new Container())->make(AutowireServiceConsumer::class)->logger->channel());
    }

    public function testFactoryAttributeBuildsThroughFactory(): void
    {
        self::assertSame('factory-made', (new Container())->make(Widget::class)->label);
    }

    public function testContextualBindingOverridesGlobalForOneConsumer(): void
    {
        $container = new Container();
        $container->alias(LoggerInterface::class, NullLogger::class);
        $container->when(NeedsLogger::class)->needs(LoggerInterface::class)->give(FileLogger::class);

        self::assertSame('file', $container->make(NeedsLogger::class)->logger->channel());
        self::assertSame('null', $container->get(LoggerInterface::class)->channel());
    }

    public function testTaggedServicesResolveAsCollection(): void
    {
        $container = new Container();
        $container->bind(AlphaReporter::class);            // tagged via #[Tag]
        $container->bind(BetaReporter::class)->tag('reporters');

        $names = array_map(
            static fn(ReporterInterface $reporter): string => $reporter->name(),
            iterator_to_array($container->tagged('reporters'), false)
        );

        sort($names);
        self::assertSame(['alpha', 'beta'], $names);
    }

    public function testLazyServiceResolvesToWorkingInstance(): void
    {
        $container = new Container();
        $container->bind(Heavy::class)->lazy();

        // Native lazy objects (PHP 8.4+) defer construction transparently; on 8.3
        // the binding resolves eagerly. Either way the instance must be usable.
        $heavy = $container->get(Heavy::class);

        self::assertInstanceOf(Heavy::class, $heavy);
        self::assertSame('pong', $heavy->ping());
    }

    public function testLazyAttributeIsHonouredWithoutExplicitLazyBinding(): void
    {
        // No ->lazy() call — the #[Lazy] class attribute alone must drive lazy resolution.
        $marked = (new Container())->get(LazyMarked::class);

        self::assertInstanceOf(LazyMarked::class, $marked);
        self::assertSame('lazy', $marked->ping());
    }

    public function testFactoryClosureCycleThrowsCircularDependency(): void
    {
        $container = new Container();
        $container->factory('svc.a', static fn(Container $c): object => $c->get('svc.b'));
        $container->factory('svc.b', static fn(Container $c): object => $c->get('svc.a'));

        $this->expectException(CircularDependencyException::class);
        $container->get('svc.a');
    }

    public function testContextualGiveWithoutNeedsThrows(): void
    {
        $this->expectException(ContainerException::class);

        (new Container())->when(NeedsLogger::class)->give(FileLogger::class);
    }

    public function testSelfBindingResolvesContainerToItself(): void
    {
        $container = new Container();

        self::assertSame($container, $container->get(Container::class));
        self::assertSame($container, $container->get(ContainerInterface::class));
        self::assertSame($container, $container->get(FactoryInterface::class));
        self::assertSame($container, $container->get(InvokerInterface::class));
    }

    public function testChildScopeInheritsParentAndIsolatesOverrides(): void
    {
        $parent = new Container();
        $parent->singleton('shared.dep', static fn(): Dependency => new Dependency());

        $child = $parent->createScope();
        $child->alias(LoggerInterface::class, FileLogger::class);

        self::assertSame($parent->get('shared.dep'), $child->get('shared.dep'));
        self::assertInstanceOf(FileLogger::class, $child->get(LoggerInterface::class));
        self::assertFalse($parent->has(LoggerInterface::class));
    }

    public function testAliasDelegatesToTargetDefinition(): void
    {
        $container = new Container();
        $container->singleton(FileLogger::class);
        $container->alias(LoggerInterface::class, FileLogger::class);

        // The alias must resolve through FileLogger's own (shared) definition,
        // not build a fresh FileLogger that bypasses it.
        self::assertSame($container->get(FileLogger::class), $container->get(LoggerInterface::class));
    }

    public function testCallAutowiresClosure(): void
    {
        self::assertSame('dep', (new Container())->call(static fn(Dependency $dep): string => $dep->marker));
    }

    public function testCallInvokableObjectMethodAndStatic(): void
    {
        $container = new Container();

        self::assertSame('hello-dep', $container->call(new Greeter()));
        self::assertSame('greet-dep', $container->call([new Greeter(), 'greet']));
        self::assertSame('shout-dep', $container->call(Greeter::class . '::shout'));
        self::assertSame('hello-dep', $container->call(Greeter::class));
    }

    public function testCallHonoursParameterOverrides(): void
    {
        self::assertSame('override', (new Container())->call(
            static fn(string $name): string => $name,
            ['name' => 'override']
        ));
    }

    public function testVariadicParameterIsNotMisinjected(): void
    {
        $container = new Container();

        self::assertSame([], $container->make(TypedVariadic::class)->deps);

        $deps = [new Dependency(), new Dependency()];
        self::assertCount(2, $container->make(TypedVariadic::class, ['deps' => $deps])->deps);
    }

    public function testDefaultValueUsedWhenParameterUnresolvable(): void
    {
        self::assertSame(5, (new Container())->make(DefaultCtor::class)->count);
    }

    public function testNullableUnresolvableDependencyBecomesNull(): void
    {
        self::assertNull((new Container())->make(NullableInterfaceCtor::class)->logger);
    }

    public function testCircularDependencyThrowsWithReadablePath(): void
    {
        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessageMatches('/Circular dependency detected/');

        (new Container())->make(CycleA::class);
    }

    public function testResolutionGuardIsExceptionSafe(): void
    {
        $container = new Container();

        try {
            $container->make(NeedsLogger::class);
            self::fail('Expected the first resolution to fail.');
        } catch (AutowireException) {
            // expected: LoggerInterface is unbound
        }

        // The guard must have been cleared; a second attempt must report the
        // same real error, never a bogus "Circular dependency".
        $this->expectException(AutowireException::class);
        $container->make(NeedsLogger::class);
    }

    public function testHasCoversExplicitRegistrationsAndSelf(): void
    {
        $container = new Container();

        self::assertFalse($container->has(Dependency::class));
        $container->bind(Dependency::class);
        self::assertTrue($container->has(Dependency::class));
        self::assertTrue($container->has(Container::class));
        self::assertFalse($container->has('unbound.id'));
    }

    public function testExtendDecoratesResolvedServiceAndStacks(): void
    {
        $container = new Container();
        $container->singleton('greeting', static fn(): string => 'hi');

        $seen = [];
        $container->extend(NoDeps::class, static function (NoDeps $noDeps) use (&$seen): NoDeps {
            $seen[] = 'a';

            return $noDeps;
        });
        $container->extend(NoDeps::class, static function (NoDeps $noDeps) use (&$seen): void {
            $seen[] = 'b';
        });

        $result = $container->get(NoDeps::class);

        self::assertInstanceOf(NoDeps::class, $result);
        self::assertSame(['a', 'b'], $seen);
    }

    public function testMissingAutowireParamThrowsContainerException(): void
    {
        $this->expectException(ContainerException::class);

        (new Container())->make(AutowireParamConsumer::class);
    }
}

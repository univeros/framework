<?php
namespace Altair\Tests\Container;

use Altair\Container\Exception\InjectionException;
use Altair\Container\Exception\InvalidArgumentException;
use Altair\Container\Container;
use Altair\Container\Definition;
use PHPUnit\Framework\TestCase;

use PHPUnit\Framework\Attributes\DataProvider;
class ContainerTest extends TestCase
{
    public function testMakeInstanceInjectsSimpleConcreteDependency(): void
    {
        $container = new Container();

        $this->assertEquals(new TestNeedsDep(new TestDependency()), $container->make(TestNeedsDep::class));
    }

    public function testMakeInstanceReturnsNewInstanceIfClassHasNoConstructor(): void
    {
        $container = new Container();
        $this->assertEquals(new TestNoConstructor, $container->make(TestNoConstructor::class));
    }

    public function testMakeInstanceReturnsAliasInstanceOnNonConcreteTypehint(): void
    {
        $container = new Container();
        $container->alias(DepInterface::class, DepImplementation::class);
        $this->assertEquals(new DepImplementation, $container->make(DepInterface::class));

        $container->alias('custom', DepImplementation::class);
        $this->assertTrue($container->isset('custom'));
        $this->assertEquals(new DepImplementation, $container->make('custom'));
    }

    public function testMakeInstanceThrowsExceptionOnInterfaceWithoutAlias(): void
    {
        $this->expectException(InjectionException::class);

        $container = new Container();
        $container->make('Altair\Tests\DepInterface');
    }

    public function testMakeInstanceThrowsExceptionOnNonConcreteCtorParamWithoutImplementation(): void
    {
        $this->expectException(InjectionException::class);

        $container = new Container;
        $container->make('Altair\Tests\RequiresInterface');
    }

    public function testMakeInstanceBuildsNonConcreteCtorParamWithAlias(): void
    {
        $container = new Container;
        $container->alias(DepInterface::class, DepImplementation::class);
        $this->assertInstanceOf(RequiresInterface::class, $container->make(RequiresInterface::class));
    }

    public function testMakeInstancePassesNullCtorParameterIfNoTypehintOrDefaultCanBeDetermined(): void
    {
        $container = new Container;
        $nullCtorParamObj = $container->make(ProvTestNoDefinitionNullDefaultClass::class);
        $this->assertEquals(new ProvTestNoDefinitionNullDefaultClass, $nullCtorParamObj);
        $this->assertEquals(null, $nullCtorParamObj->arg);
    }

    public function testMakeInstanceReturnsSharedInstanceIfAvailable(): void
    {
        $container = new Container;
        $container->define(RequiresInterface::class, new Definition(['dep' => DepImplementation::class]));
        $container->share(RequiresInterface::class);

        $injected = $container->make(RequiresInterface::class);
        $this->assertEquals('something', $injected->testDep->testProp);
        $injected->testDep->testProp = 'something else';
        $injected2 = $container->make(RequiresInterface::class);
        $this->assertEquals('something else', $injected2->testDep->testProp);
    }

    public function testMakeInstanceThrowsExceptionOnClassLoadFailure(): void
    {
        $this->expectException(InjectionException::class);

        $container = new Container;
        $container->make('ClassThatDoesntExist');
    }

    public function testMakeInstanceUsesCustomDefinitionIfSpecified(): void
    {
        $container = new Container;
        $container->define(TestNeedsDep::class, new Definition(['testDep' => TestDependency::class]));

        $injected = $container->make(TestNeedsDep::class, new Definition(['testDep' => TestDependency2::class]));
        $this->assertEquals('testVal2', $injected->testDep->testProp);
    }

    public function testMakeInstanceCustomDefinitionOverridesExistingDefinitions(): void
    {
        $container = new Container;
        $definition = (new Definition([]))
            ->addRaw('arg1', 'First argument')
            ->addRaw('arg2', 'Second argument');
        $container->define(InjectorTestChildClass::class, $definition);

        $injected = $container->make(InjectorTestChildClass::class, new Definition([':arg1' => 'Override']));
        $this->assertEquals('Override', $injected->arg1);
        $this->assertEquals('Second argument', $injected->arg2);
    }

    public function testMakeInstanceStoresShareIfMarkedWithNullInstance(): void
    {
        $container = new Container();
        $container->share(TestDependency::class);

        $instance = $container->make(TestDependency::class);
        $anotherInstance = $container->make(TestDependency::class);
        $this->assertTrue($instance instanceof TestDependency);
        $this->assertTrue($instance === $anotherInstance);
    }

    public function testMakeInstanceUsesReflectionForUnknownParamsInMultiBuildWithDeps(): void
    {
        $container = new Container();
        $object = $container->make(TestMultiDepsWithCtor::class, new Definition(['val1' => TestDependency::class]));
        $this->assertInstanceOf(TestMultiDepsWithCtor::class, $object);
        $object = $container->make(
            NoTypehintNoDefaultConstructorClass::class,
            new Definition(['val1' => TestDependency::class])
        );
        $this->assertInstanceOf(NoTypehintNoDefaultConstructorClass::class, $object);
        $this->assertEquals(null, $object->testParam);
    }

    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithoutDefinitionOrDefault(): void
    {
        $this->expectException(InjectionException::class);

        $container = new Container();
        $obj = $container->make(InjectorTestCtorParamWithNoTypehintOrDefault::class);
        $this->assertNull($obj->val);
    }

    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithoutDefinitionOrDefaultThroughAliasedTypehint(
    ): void {
        $this->expectException(InjectionException::class);

        $container = new Container();
        $container->alias(TestNoExplicitDefine::class, InjectorTestCtorParamWithNoTypehintOrDefault::class);
        $container->make(InjectorTestCtorParamWithNoTypehintOrDefaultDependent::class);
    }

    public function testTypelessDefineForDependency(): void
    {
        $thumbnailSize = 128;
        $container = new Container();
        $container->defineParameter('thumbnailSize', $thumbnailSize);

        $testClass = $container->make(RequiresDependencyWithTypelessParameters::class);
        $this->assertEquals(
            $thumbnailSize,
            $testClass->getThumbnailSize(),
            'Typeless define was not injected correctly.'
        );
    }

    public function testTypelessDefineForAliasedDependency(): void
    {
        $container = new Container();
        $container->defineParameter('val', 42);
        $container->alias(TestNoExplicitDefine::class, ProviderTestCtorParamWithNoTypehintOrDefault::class);

        $instance = $container->make(ProviderTestCtorParamWithNoTypehintOrDefaultDependent::class);
        $this->assertTrue(($instance instanceof ProviderTestCtorParamWithNoTypehintOrDefaultDependent));
    }

    public function testMakeInstanceInjectsRawParametersDirectly(): void
    {
        $container = new Container();
        $container->define(
            InjectorTestRawCtorParams::class,
            new Definition(
                [
                    ':string' => 'string',
                    ':obj' => new \StdClass,
                    ':int' => 42,
                    ':array' => [],
                    ':float' => 9.3,
                    ':bool' => true,
                    ':null' => null,
                ]
            )
        );
        $obj = $container->make(InjectorTestRawCtorParams::class);
        $this->assertIsString($obj->string);
        $this->assertInstanceOf('StdClass', $obj->obj);
        $this->assertIsInt($obj->int);
        $this->assertIsArray($obj->array);
        $this->assertIsFloat($obj->float);
        $this->assertIsBool($obj->bool);
        $this->assertNull($obj->null);
    }

    public function testMakeInstanceThrowsExceptionWhenDelegateDoes(): void
    {
        $this->expectException(\Exception::class);

        $container = new Container();
        $callable = $this->createMock(
            CallableMock::class
        );
        $container->delegate('TestDependency', $callable);
        $callable->expects($this->once())
            ->method('__invoke')
            ->will($this->throwException(new \Exception()));
        $container->make('TestDependency');
    }

    public function testMakeInstanceHandlesNamespacedClasses(): void
    {
        $container = new Container();
        $instance = $container->make(SomeClassName::class);
        $this->assertTrue($instance instanceof SomeClassName);
    }

    public function testMakeInstanceDelegate(): void
    {
        $container = new Container();
        $callable = $this->createMock(
            CallableMock::class
        );

        $callable
            ->expects($this->once())
            ->method('__invoke')
            ->willReturn(new TestDependency());

        $container->delegate(TestDependency::class, $callable);
        $obj = $container->make(TestDependency::class);
        $this->assertInstanceOf(TestDependency::class, $obj);
    }

    public function testMakeInstanceWithStringDelegate(): void
    {
        $container = new Container;
        $container->delegate('StdClass', StringStdClassDelegateMock::class);

        $obj = $container->make('StdClass');
        $this->assertEquals(42, $obj->test);
    }

    public function testMakeInstanceThrowsExceptionIfStringDelegateClassInstantiationFails(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $container = new Container();
        $container->delegate('StdClass', 'SomeClassThatDefinitelyDoesNotExistForReal');
    }

    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithNoDefinition(): void
    {
        $this->expectException(InjectionException::class);

        $container = new Container();
        $obj = $container->make(RequiresInterface::class);
    }

    public function testDefineAssignsPassedDefinition(): void
    {
        $container = new Container();
        $definition = new Definition(['dep' => DepImplementation::class]);
        $container->define(RequiresInterface::class, $definition);
        $this->assertInstanceOf(RequiresInterface::class, $container->make(RequiresInterface::class));
    }

    #[DataProvider('provideExecutionExpectations')]
    public function testProvisionedInvokables(mixed $toInvoke, mixed $definition, mixed $expectedResult): void
    {
        $container = new Container();
        $this->assertEquals($expectedResult, $container->execute($toInvoke, new Definition($definition)));
    }

    public static function provideExecutionExpectations(): array
    {
        $return = [];
        // 0 -------------------------------------------------------------------------------------->
        $toInvoke = [ExecuteClassNoDeps::class, 'execute'];
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 1 -------------------------------------------------------------------------------------->
        $toInvoke = [new ExecuteClassNoDeps, 'execute'];
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 2 -------------------------------------------------------------------------------------->
        $toInvoke = [ExecuteClassDeps::class, 'execute'];
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 3 -------------------------------------------------------------------------------------->
        $toInvoke = [new ExecuteClassDeps(new TestDependency), 'execute'];
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 4 -------------------------------------------------------------------------------------->
        $toInvoke = [ExecuteClassDepsWithMethodDeps::class, 'execute'];
        $args = [':arg' => 9382];
        $expectedResult = 9382;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 5 -------------------------------------------------------------------------------------->
        $toInvoke = ExecuteClassStaticMethod::execute(...);
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 6 -------------------------------------------------------------------------------------->
        $toInvoke = [new ExecuteClassStaticMethod, 'execute'];
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 7 -------------------------------------------------------------------------------------->
        $toInvoke = ExecuteClassStaticMethod::class . '::execute';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 8 -------------------------------------------------------------------------------------->
        $toInvoke = [ExecuteClassRelativeStaticMethod::class, 'parent::execute'];
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 9 -------------------------------------------------------------------------------------->
        $toInvoke = 'Altair\Tests\Container\testExecuteFunction';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 10 ------------------------------------------------------------------------------------->
        $toInvoke = fn(): int => 42;
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 11 ------------------------------------------------------------------------------------->
        $toInvoke = new ExecuteClassInvokable;
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 12 ------------------------------------------------------------------------------------->
        $toInvoke = ExecuteClassInvokable::class;
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 13 ------------------------------------------------------------------------------------->
        $toInvoke = ExecuteClassNoDeps::class . '::execute';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 14 ------------------------------------------------------------------------------------->
        $toInvoke = ExecuteClassDeps::class . '::execute';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 15 ------------------------------------------------------------------------------------->
        $toInvoke = ExecuteClassStaticMethod::class . '::execute';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 16 ------------------------------------------------------------------------------------->
        $toInvoke = ExecuteClassRelativeStaticMethod::class . '::parent::execute';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 17 ------------------------------------------------------------------------------------->
        $toInvoke = 'Altair\Tests\Container\testExecuteFunctionWithArg';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 18 ------------------------------------------------------------------------------------->
        $toInvoke = fn(): int => 42;
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];

        // 19 ------------------------------------------------------------------------------------->
        $object = new ReturnsCallable('new value');
        $args = [];
        $toInvoke = $object->getCallable();
        $expectedResult = 'new value';
        $return[] = [$toInvoke, $args, $expectedResult];

        // x -------------------------------------------------------------------------------------->
        return $return;
    }

    public function testInstanceMutate(): void
    {
        $container = new Container();
        $container->prepare(
            '\StdClass',
            function ($obj, $container): void {
                $obj->testval = 42;
            }
        );
        $obj = $container->make('StdClass');
        $this->assertSame(42, $obj->testval);
    }

    public function testInterfaceMutate(): void
    {
        $container = new Container();
        $container->prepare(
            SomeInterface::class,
            function ($obj, $container): void {
                $obj->testProp = 42;
            }
        );
        $obj = $container->make(PreparesImplementationTest::class);
        $this->assertSame(42, $obj->testProp);
    }
}

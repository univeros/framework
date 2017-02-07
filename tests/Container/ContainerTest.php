<?php
namespace Altair\Tests\Container;

use Altair\Container\Container;
use Altair\Container\Definition;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    public function testMakeInstanceInjectsSimpleConcreteDependency()
    {
        $container = new Container();

        $this->assertEquals(new TestNeedsDep(new TestDependency()), $container->make(TestNeedsDep::class));
    }

    public function testMakeInstanceReturnsNewInstanceIfClassHasNoConstructor()
    {
        $container = new Container();
        $this->assertEquals(new TestNoConstructor, $container->make(TestNoConstructor::class));
    }

    public function testMakeInstanceReturnsAliasInstanceOnNonConcreteTypehint()
    {
        $container = new Container();
        $container->alias(DepInterface::class, DepImplementation::class);
        $this->assertEquals(new DepImplementation, $container->make(DepInterface::class));
    }

    /**
     * @expectedException \Altair\Container\Exception\InjectionException
     */
    public function testMakeInstanceThrowsExceptionOnInterfaceWithoutAlias()
    {
        $container = new Container();
        $container->make('Altair\Tests\DepInterface');
    }

    /**
     * @expectedException \Altair\Container\Exception\InjectionException
     */
    public function testMakeInstanceThrowsExceptionOnNonConcreteCtorParamWithoutImplementation()
    {
        $container = new Container;
        $container->make('Altair\Tests\RequiresInterface');
    }

    public function testMakeInstanceBuildsNonConcreteCtorParamWithAlias()
    {
        $container = new Container;
        $container->alias(DepInterface::class, DepImplementation::class);
        $this->assertInstanceOf(RequiresInterface::class, $container->make(RequiresInterface::class));
    }

    public function testMakeInstancePassesNullCtorParameterIfNoTypehintOrDefaultCanBeDetermined()
    {
        $container = new Container;
        $nullCtorParamObj = $container->make(ProvTestNoDefinitionNullDefaultClass::class);
        $this->assertEquals(new ProvTestNoDefinitionNullDefaultClass, $nullCtorParamObj);
        $this->assertEquals(null, $nullCtorParamObj->arg);
    }

    public function testMakeInstanceReturnsSharedInstanceIfAvailable()
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

    /**
     * @expectedException \Altair\Container\Exception\InjectionException
     */
    public function testMakeInstanceThrowsExceptionOnClassLoadFailure()
    {
        $container = new Container;
        $container->make('ClassThatDoesntExist');
    }

    public function testMakeInstanceUsesCustomDefinitionIfSpecified()
    {
        $container = new Container;
        $container->define(TestNeedsDep::class, new Definition(['testDep' => TestDependency::class]));
        $injected = $container->make(TestNeedsDep::class, new Definition(['testDep' => TestDependency2::class]));
        $this->assertEquals('testVal2', $injected->testDep->testProp);
    }

    public function testMakeInstanceCustomDefinitionOverridesExistingDefinitions()
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

    public function testMakeInstanceStoresShareIfMarkedWithNullInstance()
    {
        $container = new Container();
        $container->share(TestDependency::class);
        $instance = $container->make(TestDependency::class);
        $anotherInstance = $container->make(TestDependency::class);
        $this->assertTrue($instance instanceof TestDependency);
        $this->assertTrue($instance === $anotherInstance);
    }

    public function testMakeInstanceUsesReflectionForUnknownParamsInMultiBuildWithDeps()
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

    /**
     * @expectedException \Altair\Container\Exception\InjectionException
     */
    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithoutDefinitionOrDefault()
    {
        $container = new Container();
        $obj = $container->make(InjectorTestCtorParamWithNoTypehintOrDefault::class);
        $this->assertNull($obj->val);
    }

    /**
     * @expectedException \Altair\Container\Exception\InjectionException
     */
    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithoutDefinitionOrDefaultThroughAliasedTypehint(
    ) {
        $container = new Container();
        $container->alias(TestNoExplicitDefine::class, InjectorTestCtorParamWithNoTypehintOrDefault::class);
        $container->make(InjectorTestCtorParamWithNoTypehintOrDefaultDependent::class);
    }

    public function testTypelessDefineForDependency()
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

    public function testTypelessDefineForAliasedDependency()
    {
        $container = new Container();
        $container->defineParameter('val', 42);
        $container->alias(TestNoExplicitDefine::class, ProviderTestCtorParamWithNoTypehintOrDefault::class);
        $instance = $container->make(ProviderTestCtorParamWithNoTypehintOrDefaultDependent::class);
        $this->assertTrue(($instance instanceof ProviderTestCtorParamWithNoTypehintOrDefaultDependent));
    }

    public function testMakeInstanceInjectsRawParametersDirectly()
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
        $this->assertInternalType('string', $obj->string);
        $this->assertInstanceOf('StdClass', $obj->obj);
        $this->assertInternalType('int', $obj->int);
        $this->assertInternalType('array', $obj->array);
        $this->assertInternalType('float', $obj->float);
        $this->assertInternalType('bool', $obj->bool);
        $this->assertNull($obj->null);
    }

    /**
     * @expectedException \Exception
     */
    public function testMakeInstanceThrowsExceptionWhenDelegateDoes()
    {
        $container = new Container();
        $callable = $this->createMock(
            '\Altair\Tests\Container\CallableMock'
        );
        $container->delegate('TestDependency', $callable);
        $callable->expects($this->once())
            ->method('__invoke')
            ->will($this->throwException(new \Exception()));
        $container->make('TestDependency');
    }

    public function testMakeInstanceHandlesNamespacedClasses()
    {
        $container = new Container();
        $instance = $container->make(SomeClassName::class);
        $this->assertTrue($instance instanceof SomeClassName);
    }

    public function testMakeInstanceDelegate()
    {
        $container = new Container();
        $callable = $this->createMock(
            '\Altair\Tests\Container\CallableMock'
        );

        $callable
            ->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue(new TestDependency()));

        $container->delegate(TestDependency::class, $callable);
        $obj = $container->make(TestDependency::class);
        $this->assertInstanceOf(TestDependency::class, $obj);
    }

    public function testMakeInstanceWithStringDelegate()
    {
        $container = new Container;
        $container->delegate('StdClass', StringStdClassDelegateMock::class);
        $obj = $container->make('StdClass');
        $this->assertEquals(42, $obj->test);
    }

    /**
     * @expectedException \Altair\Container\Exception\InvalidArgumentException
     */
    public function testMakeInstanceThrowsExceptionIfStringDelegateClassInstantiationFails()
    {
        $container = new Container();
        $container->delegate('StdClass', 'SomeClassThatDefinitelyDoesNotExistForReal');
    }

    /**
     * @expectedException \Altair\Container\Exception\InjectionException
     */
    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithNoDefinition()
    {
        $container = new Container();
        $obj = $container->make(RequiresInterface::class);
    }

    public function testDefineAssignsPassedDefinition()
    {
        $container = new Container();
        $definition = new Definition(['dep' => DepImplementation::class]);
        $container->define(RequiresInterface::class, $definition);
        $this->assertInstanceOf(RequiresInterface::class, $container->make(RequiresInterface::class));
    }

    /**
     * @dataProvider provideExecutionExpectations
     *
     * @param mixed $toInvoke
     * @param mixed $definition
     * @param mixed $expectedResult
     */
    public function testProvisionedInvokables($toInvoke, $definition, $expectedResult)
    {
        $container = new Container();
        $this->assertEquals($expectedResult, $container->execute($toInvoke, new Definition($definition)));
    }

    public function provideExecutionExpectations()
    {
        $return = [];
        // 0 -------------------------------------------------------------------------------------->
        $toInvoke = ['Altair\Tests\Container\ExecuteClassNoDeps', 'execute'];
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 1 -------------------------------------------------------------------------------------->
        $toInvoke = [new ExecuteClassNoDeps, 'execute'];
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 2 -------------------------------------------------------------------------------------->
        $toInvoke = ['Altair\Tests\Container\ExecuteClassDeps', 'execute'];
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 3 -------------------------------------------------------------------------------------->
        $toInvoke = [new ExecuteClassDeps(new TestDependency), 'execute'];
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 4 -------------------------------------------------------------------------------------->
        $toInvoke = ['Altair\Tests\Container\ExecuteClassDepsWithMethodDeps', 'execute'];
        $args = [':arg' => 9382];
        $expectedResult = 9382;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 5 -------------------------------------------------------------------------------------->
        $toInvoke = ['Altair\Tests\Container\ExecuteClassStaticMethod', 'execute'];
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 6 -------------------------------------------------------------------------------------->
        $toInvoke = [new ExecuteClassStaticMethod, 'execute'];
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 7 -------------------------------------------------------------------------------------->
        $toInvoke = 'Altair\Tests\Container\ExecuteClassStaticMethod::execute';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 8 -------------------------------------------------------------------------------------->
        $toInvoke = ['Altair\Tests\Container\ExecuteClassRelativeStaticMethod', 'parent::execute'];
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 9 -------------------------------------------------------------------------------------->
        $toInvoke = 'Altair\Tests\Container\testExecuteFunction';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 10 ------------------------------------------------------------------------------------->
        $toInvoke = function () {
            return 42;
        };
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 11 ------------------------------------------------------------------------------------->
        $toInvoke = new ExecuteClassInvokable;
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 12 ------------------------------------------------------------------------------------->
        $toInvoke = 'Altair\Tests\Container\ExecuteClassInvokable';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 13 ------------------------------------------------------------------------------------->
        $toInvoke = 'Altair\Tests\Container\ExecuteClassNoDeps::execute';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 14 ------------------------------------------------------------------------------------->
        $toInvoke = 'Altair\Tests\Container\ExecuteClassDeps::execute';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 15 ------------------------------------------------------------------------------------->
        $toInvoke = 'Altair\Tests\Container\ExecuteClassStaticMethod::execute';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 16 ------------------------------------------------------------------------------------->
        $toInvoke = 'Altair\Tests\Container\ExecuteClassRelativeStaticMethod::parent::execute';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 17 ------------------------------------------------------------------------------------->
        $toInvoke = 'Altair\Tests\Container\testExecuteFunctionWithArg';
        $args = [];
        $expectedResult = 42;
        $return[] = [$toInvoke, $args, $expectedResult];
        // 18 ------------------------------------------------------------------------------------->
        $toInvoke = function () {
            return 42;
        };
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

    public function testInstanceMutate()
    {
        $container = new Container();
        $container->prepare(
            '\StdClass',
            function ($obj, $container) {
                $obj->testval = 42;
            }
        );
        $obj = $container->make('StdClass');
        $this->assertSame(42, $obj->testval);
    }

    public function testInterfaceMutate()
    {
        $container = new Container();
        $container->prepare(
            SomeInterface::class,
            function ($obj, $container) {
                $obj->testProp = 42;
            }
        );
        $obj = $container->make(PreparesImplementationTest::class);
        $this->assertSame(42, $obj->testProp);
    }
}

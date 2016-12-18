<?php
namespace Altair\Tests\Container;

use Altair\Container\Container;
use Altair\Container\Definition;

class ContainerTest extends \PHPUnit_Framework_TestCase
{
    public function testMakeInstanceInjectsSimpleConcreteDependency()
    {
        $container = new Container();

        $this->assertEquals(new TestNeedsDep(new TestDependency()), $container->make(TestNeedsDep::class));
    }

    public function testMakeInstanceReturnsNewInstanceIfClassHasNoConstructor()
    {
        $injector = new Container();
        $this->assertEquals(new TestNoConstructor, $injector->make(TestNoConstructor::class));
    }

    public function testMakeInstanceReturnsAliasInstanceOnNonConcreteTypehint()
    {
        $injector = new Container();
        $injector->alias(DepInterface::class, DepImplementation::class);
        $this->assertEquals(new DepImplementation, $injector->make(DepInterface::class));
    }

    /**
     * @expectedException \Altair\Container\Exception\InjectionException
     */
    public function testMakeInstanceThrowsExceptionOnInterfaceWithoutAlias()
    {
        $injector = new Container();
        $injector->make('Altair\Tests\DepInterface');
    }

    /**
     * @expectedException \Altair\Container\Exception\InjectionException
     */
    public function testMakeInstanceThrowsExceptionOnNonConcreteCtorParamWithoutImplementation()
    {
        $injector = new Container;
        $injector->make('Altair\Tests\RequiresInterface');
    }

    public function testMakeInstanceBuildsNonConcreteCtorParamWithAlias()
    {
        $injector = new Container;
        $injector->alias(DepInterface::class, DepImplementation::class);
        $this->assertInstanceOf(RequiresInterface::class, $injector->make(RequiresInterface::class));
    }

    public function testMakeInstancePassesNullCtorParameterIfNoTypehintOrDefaultCanBeDetermined()
    {
        $injector = new Container;
        $nullCtorParamObj = $injector->make(ProvTestNoDefinitionNullDefaultClass::class);
        $this->assertEquals(new ProvTestNoDefinitionNullDefaultClass, $nullCtorParamObj);
        $this->assertEquals(null, $nullCtorParamObj->arg);
    }

    public function testMakeInstanceReturnsSharedInstanceIfAvailable()
    {
        $injector = new Container;
        $injector->define(RequiresInterface::class, new Definition(['dep' => DepImplementation::class]));
        $injector->share(RequiresInterface::class);
        $injected = $injector->make(RequiresInterface::class);
        $this->assertEquals('something', $injected->testDep->testProp);
        $injected->testDep->testProp = 'something else';
        $injected2 = $injector->make(RequiresInterface::class);
        $this->assertEquals('something else', $injected2->testDep->testProp);
    }

    /**
     * @expectedException \Altair\Container\Exception\InjectionException
     */
    public function testMakeInstanceThrowsExceptionOnClassLoadFailure()
    {
        $injector = new Container;
        $injector->make('ClassThatDoesntExist');
    }

    public function testMakeInstanceUsesCustomDefinitionIfSpecified()
    {
        $injector = new Container;
        $injector->define(TestNeedsDep::class, new Definition(['testDep' => TestDependency::class]));
        $injected = $injector->make(TestNeedsDep::class, new Definition(['testDep' => TestDependency2::class]));
        $this->assertEquals('testVal2', $injected->testDep->testProp);
    }

    public function testMakeInstanceCustomDefinitionOverridesExistingDefinitions()
    {
        $injector = new Container;
        $definition = (new Definition([]))
            ->addRaw('arg1', 'First argument')
            ->addRaw('arg2', 'Second argument');
        $injector->define(InjectorTestChildClass::class, $definition);

        $injected = $injector->make(InjectorTestChildClass::class, new Definition([':arg1' => 'Override']));
        $this->assertEquals('Override', $injected->arg1);
        $this->assertEquals('Second argument', $injected->arg2);
    }

    public function testMakeInstanceStoresShareIfMarkedWithNullInstance()
    {
        $injector = new Container();
        $injector->share(TestDependency::class);
        $injector->make(TestDependency::class);
    }

    public function testMakeInstanceUsesReflectionForUnknownParamsInMultiBuildWithDeps()
    {
        $injector = new Container();
        $object = $injector->make(TestMultiDepsWithCtor::class, new Definition(['val1' => TestDependency::class]));
        $this->assertInstanceOf(TestMultiDepsWithCtor::class, $object);
        $object = $injector->make(
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
        $injector = new Container();
        $obj = $injector->make(InjectorTestCtorParamWithNoTypehintOrDefault::class);
        $this->assertNull($obj->val);
    }

    /**
     * @expectedException \Altair\Container\Exception\InjectionException
     */
    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithoutDefinitionOrDefaultThroughAliasedTypehint(
    )
    {
        $injector = new Container();
        $injector->alias(TestNoExplicitDefine::class, InjectorTestCtorParamWithNoTypehintOrDefault::class);
        $injector->make(InjectorTestCtorParamWithNoTypehintOrDefaultDependent::class);
    }

    public function testTypelessDefineForDependency()
    {
        $thumbnailSize = 128;
        $injector = new Container();
        $injector->defineParameter('thumbnailSize', $thumbnailSize);
        $testClass = $injector->make(RequiresDependencyWithTypelessParameters::class);
        $this->assertEquals(
            $thumbnailSize,
            $testClass->getThumbnailSize(),
            'Typeless define was not injected correctly.'
        );
    }

    public function testTypelessDefineForAliasedDependency()
    {
        $injector = new Container();
        $injector->defineParameter('val', 42);
        $injector->alias(TestNoExplicitDefine::class, ProviderTestCtorParamWithNoTypehintOrDefault::class);
        $injector->make(ProviderTestCtorParamWithNoTypehintOrDefaultDependent::class);
    }

    public function testMakeInstanceInjectsRawParametersDirectly()
    {
        $injector = new Container();
        $injector->define(
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
        $obj = $injector->make(InjectorTestRawCtorParams::class);
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
        $injector = new Container();
        $callable = $this->createMock(
            '\Altair\Tests\Container\CallableMock'
        );
        $injector->delegate('TestDependency', $callable);
        $callable->expects($this->once())
            ->method('__invoke')
            ->will($this->throwException(new \Exception()));
        $injector->make('TestDependency');
    }

    public function testMakeInstanceHandlesNamespacedClasses()
    {
        $injector = new Container();
        $injector->make(SomeClassName::class);
    }

    public function testMakeInstanceDelegate()
    {
        $injector = new Container();
        $callable = $this->createMock(
            '\Altair\Tests\Container\CallableMock'
        );

        $callable
            ->expects($this->once())
            ->method('__invoke')
            ->will($this->returnValue(new TestDependency()));

        $injector->delegate(TestDependency::class, $callable);
        $obj = $injector->make(TestDependency::class);
        $this->assertInstanceOf(TestDependency::class, $obj);
    }

    public function testMakeInstanceWithStringDelegate()
    {
        $injector = new Container;
        $injector->delegate('StdClass', StringStdClassDelegateMock::class);
        $obj = $injector->make('StdClass');
        $this->assertEquals(42, $obj->test);
    }

    /**
     * @expectedException \Altair\Container\Exception\InvalidArgumentException
     */
    public function testMakeInstanceThrowsExceptionIfStringDelegateClassInstantiationFails()
    {
        $injector = new Container();
        $injector->delegate('StdClass', 'SomeClassThatDefinitelyDoesNotExistForReal');
    }

    /**
     * @expectedException \Altair\Container\Exception\InjectionException
     */
    public function testMakeInstanceThrowsExceptionOnUntypehintedParameterWithNoDefinition()
    {
        $injector = new Container();
        $obj = $injector->make(RequiresInterface::class);
    }

    public function testDefineAssignsPassedDefinition()
    {
        $injector = new Container();
        $definition = new Definition(['dep' => DepImplementation::class]);
        $injector->define(RequiresInterface::class, $definition);
        $this->assertInstanceOf(RequiresInterface::class, $injector->make(RequiresInterface::class));
    }


    // TODO:  MISSING

    /**
     * @dataProvider provideExecutionExpectations
     */
    public function testProvisionedInvokables($toInvoke, $definition, $expectedResult)
    {
        $injector = new Container();
        $this->assertEquals($expectedResult, $injector->execute($toInvoke, new Definition($definition)));
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
}

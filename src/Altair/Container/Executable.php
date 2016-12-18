<?php
namespace Altair\Container;

use ReflectionFunction;
use InvalidArgumentException;
use ReflectionMethod;
use Closure;
use ReflectionFunctionAbstract;


class Executable
{
    /**
     * @var ReflectionFunctionAbstract|ReflectionFunction
     */
    protected $callableReflection;
    /**
     * @var mixed|null The object to invoke the method on if $reflectionFunction is a ReflectionMethod. In
     * case of static methods it can be null.
     */
    protected $object;

    /**
     * Executable constructor.
     *
     * @param ReflectionFunctionAbstract $reflectionFunction
     * @param mixed|null $object The object to invoke the method on if $reflectionFunction is a ReflectionMethod. In
     * case of static methods, you can pass null to this parameter.
     *
     */
    public function __construct(ReflectionFunctionAbstract $reflectionFunction, $object = null)
    {
        if ($reflectionFunction instanceof ReflectionMethod) {
            $this->setMethodCallable($reflectionFunction, $object);
        } else {
            $this->callableReflection = $reflectionFunction;
        }
    }

    /**
     * @return ReflectionFunctionAbstract
     */
    public function getCallableReflection(): ReflectionFunctionAbstract
    {
        return $this->callableReflection;
    }

    /**
     * @return mixed|null
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * @return mixed
     */
    public function __invoke()
    {
        $args = func_get_args();
        $reflection = $this->callableReflection;
        if ($reflection instanceof ReflectionMethod) {
            return $reflection->invokeArgs($this->object, $args);
        }

        return $this->callableReflection->isClosure()
            ? $this->invokeClosure($reflection, $args)
            : $reflection->invokeArgs($args);
    }

    /**
     * @param ReflectionMethod $reflection
     * @param mixed $object
     */
    protected function setMethodCallable(ReflectionMethod $reflection, $object)
    {
        if (is_object($object)) {
            $this->callableReflection = $reflection;
            $this->object = $object;
        } elseif ($reflection->isStatic()) {
            $this->callableReflection = $reflection;
        } else {
            throw new InvalidArgumentException('ReflectionMethod callables must specify an invocation object');
        }
    }

    /**
     * @param ReflectionFunction|ReflectionFunctionAbstract $reflection
     * @param array $args
     *
     * @return mixed
     */
    protected function invokeClosure(ReflectionFunction $reflection, array $args)
    {

        $scope = $reflection->getClosureScopeClass();

        $closure = Closure::bind(
            $reflection->getClosure(),
            $reflection->getClosureThis(),
            $scope ? $scope->name : null
        );

        return call_user_func_array($closure, $args);

    }
}

<?php
namespace Altair\Container\Builder;

use Altair\Container\Contracts\ExecutableBuilderInterface;
use Altair\Container\Exception\InjectionException;
use Altair\Container\Executable;
use Altair\Container\Injector;
use ReflectionException;
use Closure;

class ExecutableBuilder implements ExecutableBuilderInterface
{
    protected $injector;

    public function __construct(Injector $injector)
    {
        $this->injector = $injector;
    }

    public function build($callableOrMethodString): Executable
    {

        list($reflectionFunction, $invocationObject) = $this->buildExecutableStructure($callableOrMethodString);

        return new Executable($reflectionFunction, $invocationObject);
    }

    /**
     * @inheritdoc
     */
    public function isExecutable($executable): bool
    {
        return is_callable($executable)
            || (is_string($executable) && method_exists($executable, '__invoke'))
            || (is_array($executable)
                && isset($executable[0], $executable[1])
                && method_exists($executable[0], $executable[1]));
    }

    protected function buildExecutableStructure($callableOrMethodString): array
    {
        try {
            if (is_string($callableOrMethodString)) {
                $executableStructure = $this->buildExecutableStructureFromString($callableOrMethodString);
            } elseif ($callableOrMethodString instanceof Closure) {
                $callableReflection = $this->injector->getReflector()->getFunction($callableOrMethodString);
                $executableStructure = [$callableReflection, null];
            } elseif (is_object($callableOrMethodString) && is_callable($callableOrMethodString)) {
                $invocationObject = $callableOrMethodString;
                $callableReflection = $this->injector->getReflector()->getMethod($invocationObject, '__invoke');
                $executableStructure = [$callableReflection, $invocationObject];
            } elseif (is_array($callableOrMethodString)
                && isset($callableOrMethodString[0], $callableOrMethodString[1])
                && count($callableOrMethodString) === 2
            ) {
                $executableStructure = $this->buildExecutableStructureFromArray($callableOrMethodString);
            } else {
                throw new InjectionException('Invalid callable or method string');
            }
        } catch (ReflectionException $e) {
            throw new InjectionException('Invalid callable or method string');
        }

        return $executableStructure;
    }

    protected function buildExecutableStructureFromString(string $executableString): array
    {
        if (function_exists($executableString)) {
            $callableReflection = $this->injector->getReflector()->getFunction($executableString);
            $executableStructure = [$callableReflection, null];

        } elseif (method_exists($executableString, '__invoke')) {
            $invocationObject = $this->injector->make($executableString);
            $callableReflection = $this->injector->getReflector()->getMethod($invocationObject, '__invoke');
            $executableStructure = [$callableReflection, $invocationObject];

        } elseif (strpos($executableString, '::') !== false) {
            list($class, $method) = explode('::', $executableString, 2);
            $executableStructure = $this->buildExecutableStructureFromClassMethodCallable($class, $method);

        } else {
            throw new InjectionException('Invalid callable string');
        }

        return $executableStructure;
    }

    protected function buildExecutableStructureFromClassMethodCallable($class, $method): array
    {
        $relativeStaticMethodStartPos = strpos($method, 'parent::');

        if ($relativeStaticMethodStartPos === 0) {
            $childReflection = $this->injector->getReflector()->getClass($class);
            $class = $childReflection->getParentClass()->name;
            $method = substr($method, $relativeStaticMethodStartPos + 8);
        }
        list($className,) = $this->injector->getAliases()->resolve($class);
        $reflectionMethod = $this->injector->getReflector()->getMethod($className, $method);

        if ($reflectionMethod->isStatic()) {
            return [$reflectionMethod, null];
        }
        $instance = $this->injector->make($className);
        // If the class was delegated, the instance may not be of the type
        // $class but some other type. We need to get the reflection on the
        // actual class to be able to call the method correctly.
        $reflectionMethod = $this->injector->getReflector()->getMethod($instance, $method);

        return [$reflectionMethod, $instance];
    }

    protected function buildExecutableStructureFromArray(array $executableArray): array
    {
        list($classOrObject, $method) = $executableArray;
        if (is_object($classOrObject) && method_exists($classOrObject, $method)) {
            $callableReflection = $this->injector->getReflector()->getMethod($classOrObject, $method);
            $executableStructure = [$callableReflection, $classOrObject];
        } elseif (is_string($classOrObject)) {
            $executableStructure = $this->buildExecutableStructureFromClassMethodCallable($classOrObject, $method);
        } else {
            throw new InjectionException('Invalid callable array');
        }

        return $executableStructure;

    }
}

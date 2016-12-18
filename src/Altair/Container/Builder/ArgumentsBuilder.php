<?php
namespace Altair\Container\Builder;

use Altair\Container\Contracts\BuilderInterface;
use Altair\Container\Definition;
use Altair\Container\Exception\InjectionException;
use Altair\Container\Injector;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

class ArgumentsBuilder implements BuilderInterface
{

    protected $injector;

    public function __construct(Injector $injector)
    {
        $this->injector = $injector;
    }

    public function build(
        ReflectionFunctionAbstract $reflectionFunction,
        Definition $definition,
        array $reflectionParameters = null
    ) {
        $arguments = [];

        if (!isset($reflectionParameters)) {
            $reflectionParameters = $reflectionFunction->getParameters();
        }
        foreach ($reflectionParameters as $position => $reflectionParameter) {
            $name = $reflectionParameter->name;

            if ($definition->hasIndex($position)) {
                $argument = $definition->getIndexed($position);
            } elseif ($definition->has($name)) {
                $argument = $this->injector->make($definition->get($name));
            } elseif ($definition->hasRaw($name)) {
                $argument = $definition->getRaw($name);
            } elseif ($definition->hasDelegate($name)) {
                $argument = $this->buildArgumentFromDelegate($name, $definition->getDelegate($name));
            } elseif ($definition->hasClassDefinition($name)) {
                $argument = $this->buildArgumentFromClassDefinition($definition->getClassDefinition($name));
            } else {
                $argument = $this->buildArgumentFromTypeHint($reflectionFunction, $reflectionParameter);
                if (!$argument) {
                    $argument = $this->buildArgumentFromReflectionParameter($reflectionParameter);
                }
            }
            $arguments[] = $argument;
        }

        return $arguments;
    }

    /**
     * @param ReflectionFunctionAbstract $reflectionFunction
     * @param ReflectionParameter $reflectionParameter
     *
     * @return mixed|null|object
     */
    protected function buildArgumentFromTypeHint(
        ReflectionFunctionAbstract $reflectionFunction,
        ReflectionParameter $reflectionParameter
    ) {
        $typeHint = $this->injector->getReflector()->getParameterTypeHint($reflectionFunction, $reflectionParameter);

        if (!$typeHint) {
            $object = null;
        } elseif ($reflectionParameter->isDefaultValueAvailable()) {
            $object = $this->injector->isset($typeHint)
                ? $this->injector->make($typeHint)
                : $reflectionParameter->getDefaultValue();
        } else {
            $object = $this->injector->make($typeHint);
        }

        return $object;
    }

    /**
     * @param ReflectionParameter $reflectionParameter
     *
     * @return mixed|null
     * @throws InjectionException
     */
    protected function buildArgumentFromReflectionParameter(ReflectionParameter $reflectionParameter)
    {
        if($this->injector->getParameterDefinitions()->hasKey($reflectionParameter->name)) {
            $argument = $this->injector->getParameterDefinitions()->get($reflectionParameter->name);
        } elseif($reflectionParameter->isDefaultValueAvailable()) {
            $argument = $reflectionParameter->getDefaultValue();
        } elseif($reflectionParameter->isOptional()) {
            $argument = null;
        } else {
            $reflectionFunction = $reflectionParameter->getDeclaringFunction();
            $class = $reflectionFunction instanceof ReflectionMethod
                ? $reflectionFunction->getDeclaringClass()->name . '::'
                : '';

            $function = $class . $reflectionFunction->name;

            throw new InjectionException(
                sprintf(
                    "No definition available to provision typeless parameter \$%s at position %d in %s()",
                    $reflectionParameter->name,
                    $reflectionParameter->getPosition(),
                    $function
                )
            );
        }

        return $argument;
    }

    protected function buildArgumentFromDelegate(string $name, $callableOrMethodString)
    {
        if ($this->injector->getExecutableBuilder()->isExecutable($callableOrMethodString) === false) {
            throw new InjectionException("Unable to create argument '$name' from delegate.");
        }

        $executable = $this->injector->getExecutableBuilder()->build($callableOrMethodString);

        return $executable($name, $this->injector);
    }

    protected function buildArgumentFromClassDefinition(array $definition)
    {
        list($class, $definition) = $definition;

        if (is_array($definition)) {
            $definition = new Definition($definition);
        }

        return $this->injector->make($class, $definition);
    }
}

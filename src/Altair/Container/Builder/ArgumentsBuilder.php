<?php
namespace Altair\Container\Builder;

use Altair\Container\Container;
use Altair\Container\Contracts\BuilderInterface;
use Altair\Container\Definition;
use Altair\Container\Exception\InjectionException;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

class ArgumentsBuilder implements BuilderInterface
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * ArgumentsBuilder constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param ReflectionFunctionAbstract $reflectionFunction
     * @param Definition $definition
     * @param ReflectionParameter[]|null $reflectionParameters
     *
     * @return array
     */
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
                $argument = $this->container->make($definition->get($name));
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
        $typeHint = $this->container->getReflector()->getParameterTypeHint($reflectionFunction, $reflectionParameter);

        if (!$typeHint) {
            $object = null;
        } elseif ($reflectionParameter->isDefaultValueAvailable()) {
            $object = $this->container->isset($typeHint)
                ? $this->container->make($typeHint)
                : $reflectionParameter->getDefaultValue();
        } else {
            $object = $this->container->make($typeHint);
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
        if ($this->container->getParameterDefinitions()->hasKey($reflectionParameter->name)) {
            $argument = $this->container->getParameterDefinitions()->get($reflectionParameter->name);
        } elseif ($reflectionParameter->isDefaultValueAvailable()) {
            $argument = $reflectionParameter->getDefaultValue();
        } elseif ($reflectionParameter->isOptional()) {
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

    /**
     * @param string $name
     * @param $callableOrMethodString
     *
     * @return mixed
     * @throws InjectionException
     */
    protected function buildArgumentFromDelegate(string $name, $callableOrMethodString)
    {
        if ($this->container->getExecutableBuilder()->isExecutable($callableOrMethodString) === false) {
            throw new InjectionException("Unable to create argument '$name' from delegate.");
        }

        $executable = $this->container->getExecutableBuilder()->build($callableOrMethodString);

        return $executable($name, $this->container);
    }

    /**
     * @param array $definition
     *
     * @return mixed|null|object
     */
    protected function buildArgumentFromClassDefinition(array $definition)
    {
        list($class, $definition) = $definition;

        if (is_array($definition)) {
            $definition = new Definition($definition);
        }

        return $this->container->make($class, $definition);
    }
}

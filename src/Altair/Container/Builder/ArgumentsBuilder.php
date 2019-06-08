<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Builder;

use Altair\Container\Container;
use Altair\Container\Contracts\BuilderInterface;
use Altair\Container\Definition;
use Altair\Container\Exception\InjectionException;
use ReflectionException;
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
     * @param array|null $reflectionParameters
     * @throws InjectionException
     * @throws ReflectionException
     * @return array
     */
    public function build(
        ReflectionFunctionAbstract $reflectionFunction,
        Definition $definition,
        array $reflectionParameters = null
    ): array {
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
     * @throws InjectionException
     * @throws \ReflectionException
     * @return mixed|object|null
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
     * @throws InjectionException
     * @throws \ReflectionException
     * @return mixed|null
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
                    'No definition available to provision typeless parameter \$%s at position %d in %s()',
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
     * @throws InjectionException
     * @return mixed
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
     * @throws InjectionException
     * @return mixed|object|null
     */
    protected function buildArgumentFromClassDefinition(array $definition)
    {
        [$class, $arguments] = $definition;

        if (is_array($arguments)) {
            $arguments = new Definition($arguments);
        }

        return $this->container->make($class, $arguments);
    }
}

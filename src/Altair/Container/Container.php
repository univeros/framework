<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container;

use Altair\Container\Builder\ArgumentsBuilder;
use Altair\Container\Builder\ExecutableBuilder;
use Altair\Container\Collection\AliasesCollection;
use Altair\Container\Collection\ClassDefinitionsCollection;
use Altair\Container\Collection\DelegatesCollection;
use Altair\Container\Collection\ParameterDefinitionsCollection;
use Altair\Container\Collection\PreparesCollection;
use Altair\Container\Collection\SharesCollection;
use Altair\Container\Contracts\ReflectionInterface;
use Altair\Container\Exception\InjectionException;
use Altair\Container\Exception\InvalidArgumentException;
use Altair\Container\Exception\NotFoundException;
use Altair\Container\Reflection\CachedReflection;
use Altair\Container\Traits\NameNormalizerTrait;
use Altair\Structure\Map;
use Override;
use Psr\Container\ContainerInterface;
use ReflectionException;
use ReflectionMethod;

class Container implements ContainerInterface
{
    use NameNormalizerTrait;

    protected ReflectionInterface $reflector;

    protected AliasesCollection $aliases;

    protected ClassDefinitionsCollection $classDefinitions;

    protected ParameterDefinitionsCollection $parameterDefinitions;

    protected SharesCollection $shares;

    protected PreparesCollection $prepares;

    protected DelegatesCollection $delegates;

    /**
     * @var array<string, int>
     */
    protected $making = [];

    protected ExecutableBuilder $executableBuilder;

    protected ArgumentsBuilder $argumentsBuilder;

    /**
     * Container constructor.
     */
    public function __construct(
        ?ReflectionInterface $reflector = null,
        ?AliasesCollection $aliasesCollection = null,
        ?ClassDefinitionsCollection $classDefinitionsCollection = null,
        ?ParameterDefinitionsCollection $parameterDefinitionsCollection = null,
        ?SharesCollection $sharesCollection = null,
        ?PreparesCollection $preparesCollection = null,
        ?DelegatesCollection $delegatesCollection = null,
        ?ExecutableBuilder $executableBuilder = null,
        ?ArgumentsBuilder $argumentsBuilder = null
    ) {
        $this->reflector = $reflector ?? new CachedReflection();
        $this->aliases = $aliasesCollection ?? new AliasesCollection();
        $this->classDefinitions = $classDefinitionsCollection ?? new ClassDefinitionsCollection();
        $this->parameterDefinitions = $parameterDefinitionsCollection ?? new ParameterDefinitionsCollection();
        $this->shares = $sharesCollection ?? new SharesCollection();
        $this->prepares = $preparesCollection ?? new PreparesCollection();
        $this->delegates = $delegatesCollection ?? new DelegatesCollection();
        $this->executableBuilder = $executableBuilder ?? new ExecutableBuilder($this);
        $this->argumentsBuilder = $argumentsBuilder ?? new ArgumentsBuilder($this);
    }

    /**
     * @inheritDoc
     */
    public function __clone()
    {
        $this->making = [];
    }

    #[Override]
    public function get(string $id): mixed
    {
        if (!$this->isset($id)) {
            throw new NotFoundException(\sprintf('Class or alias %s not found.', $id));
        }

        return $this->make($id);
    }

    #[Override]
    public function has(string $id): bool
    {
        return $this->isset($id);
    }

    /**
     * Define instantiation directives for the specified class
     *
     * @param string $name The class (or alias) whose constructor arguments we wish to define
     * @param Definition $definition A definition class that holds map of  values/instructions
     */
    public function define(string $name, Definition $definition): Container
    {
        [, $normalizedClass] = $this->aliases->resolve($name);
        $this->classDefinitions->put($normalizedClass, $definition);

        return $this;
    }

    /**
     * Assign a global default value for all parameters named $paramName
     *
     * Global parameter definitions are only used for parameters with no typehint, pre-defined or
     * call-time definition.
     *
     * @param string $paramName The parameter name for which this value applies
     * @param mixed $value The value to inject for this parameter name
     */
    public function defineParameter($paramName, mixed $value): Container
    {
        $this->parameterDefinitions->put($paramName, $value);

        return $this;
    }

    /**
     * Define an alias for all occurrences of a given typehint
     *
     * Use this method to specify implementation classes for interface and abstract class typehints.
     *
     * @param string $original The typehint to replace
     * @param string $alias The implementation name
     *
     * @throws InvalidArgumentException if any argument is empty or not a string
     */
    public function alias(string $original, string $alias): Container
    {
        $this->aliases->define($original, $alias, $this->shares);

        return $this;
    }

    /**
     * Share the specified class/instance across the Container context
     *
     * @param mixed $nameOrInstance The class or object to share
     *
     * @throws InvalidArgumentException if $nameOrInstance is not a string or an object
     */
    public function share(mixed $nameOrInstance): Container
    {
        if (\is_string($nameOrInstance)) {
            $this->shares->shareClass($nameOrInstance, $this->aliases);
        } elseif (\is_object($nameOrInstance)) {
            $this->shares->shareInstance($nameOrInstance, $this->aliases);
        } else {
            throw new InvalidArgumentException(
                \sprintf(
                    '%s::share() requires a string class name or object instance at Argument 1; %s specified',
                    self::class,
                    \gettype($nameOrInstance)
                )
            );
        }

        return $this;
    }

    /**
     * Register a prepare callable to modify/prepare objects of type $name after instantiation
     *
     * Any callable or provisionable invokable may be specified. Preparers are passed two
     * arguments: the instantiated object to be mutated and the current Container instance.
     *
     * @param mixed $callableOrMethodStr Any callable or provisionable invokable method
     *
     * @throws InvalidArgumentException if $callableOrMethodStr is not a callable.
     *
     */
    public function prepare(string $name, mixed $callableOrMethodStr): Container
    {
        if ($this->executableBuilder->isExecutable($callableOrMethodStr) === false) {
            throw new InvalidArgumentException('Invalid invokable: callable or provisional string required');
        }

        [, $normalizedClass] = $this->aliases->resolve($name);
        $this->prepares[$normalizedClass] = $callableOrMethodStr;

        return $this;
    }

    /**
     * Delegate the creation of $name instances to the specified callable
     *
     * @param mixed $callableOrMethodStr Any callable or provisionable invokable method
     * @throws InvalidArgumentException if $callableOrMethodStr is not a callable.
     */
    public function delegate(string $name, mixed $callableOrMethodStr): Container
    {
        if ($this->executableBuilder->isExecutable($callableOrMethodStr) === false) {
            $errorDetail = '';
            if (\is_string($callableOrMethodStr)) {
                $errorDetail = \sprintf(" but received '%s'", $callableOrMethodStr);
            } elseif (\is_array($callableOrMethodStr) && 2 === \count($callableOrMethodStr)
                      &&
                      \array_key_exists(0, $callableOrMethodStr) &&
                \array_key_exists(1, $callableOrMethodStr)
            ) {
                if (\is_string($callableOrMethodStr[0]) && \is_string($callableOrMethodStr[1])) {
                    $errorDetail = " but received ['" . $callableOrMethodStr[0] . "', '" . $callableOrMethodStr[1] . "']";
                }
            }

            throw new InvalidArgumentException(
                \sprintf(
                    '%s::delegate expects a valid callable or executable class::method string at Argument 2%s',
                    self::class,
                    $errorDetail
                )
            );
        }

        $normalizedName = $this->normalizeName($name);
        $this->delegates->put($normalizedName, $callableOrMethodStr);

        return $this;
    }

    /**
     * Instantiate/provision a class instance
     * @throws InjectionException
     * @throws ReflectionException
     * @return mixed|object|null
     */
    public function make(string $name, ?Definition $definition = null)
    {
        [$className, $normalizedClass] = $this->aliases->resolve($name);

        if (isset($this->making[$normalizedClass])) {
            throw new InjectionException(
                \sprintf(
                    "Cyclic dependency detected while provisioning '%s'",
                    $className
                )
            );
        }

        $definition ??= new Definition([]);

        $this->making[$normalizedClass] = \count($this->making);

        if (isset($this->shares[$normalizedClass])) {
            unset($this->making[$normalizedClass]);

            return $this->shares->get($normalizedClass);
        }

        if (isset($this->delegates[$normalizedClass])) {
            $executable = $this->executableBuilder->build($this->delegates->get($normalizedClass));
            $reflectionFunction = $executable->getCallableReflection();

            $arguments = $this->argumentsBuilder->build($reflectionFunction, $definition);
            $object = $executable->__invoke(...$arguments);
        } else {
            $object = $this->provisionInstance($className, $normalizedClass, $definition);
        }

        $object = $this->prepareInstance($object, $normalizedClass);

        if ($this->shares->hasKey($normalizedClass)) {
            $this->shares->put($normalizedClass, $object);
        }

        unset($this->making[$normalizedClass]);

        return $object;
    }

    /**
     * Invoke the specified callable or class::method string, provisioning dependencies along the way
     *
     * @throws InjectionException
     * @throws ReflectionException
     */
    public function execute(mixed $callableOrMethodString, ?Definition $definition = null): mixed
    {
        $executable = $this->executableBuilder->build($callableOrMethodString);
        $definition ??= new Definition([]);
        $arguments = $this->argumentsBuilder->build($executable->getCallableReflection(), $definition);

        return $executable->__invoke(...$arguments);
    }

    public function getReflector(): ReflectionInterface
    {
        return $this->reflector;
    }

    public function getAliases(): AliasesCollection
    {
        return $this->aliases;
    }

    public function getClassDefinitions(): ClassDefinitionsCollection
    {
        return $this->classDefinitions;
    }

    public function getParameterDefinitions(): ParameterDefinitionsCollection
    {
        return $this->parameterDefinitions;
    }

    public function getShares(): SharesCollection
    {
        return $this->shares;
    }

    public function getDelegates(): DelegatesCollection
    {
        return $this->delegates;
    }

    /**
     * Read-only access to registered prepare callables, keyed by normalized class name.
     *
     * Walking this collection is safe — it never triggers instantiation.
     */
    public function getPrepares(): PreparesCollection
    {
        return $this->prepares;
    }

    public function isset(string $name): bool
    {
        $name = $this->normalizeName($name);

        return isset($this->aliases[$name]) ||
            isset($this->delegates[$name]) ||
            isset($this->shares[$name]);
    }

    public function getExecutableBuilder(): ExecutableBuilder
    {
        return $this->executableBuilder;
    }

    public function getArgumentsBuilder(): ArgumentsBuilder
    {
        return $this->argumentsBuilder;
    }

    /**
     * @throws InjectionException
     * @return mixed
     */
    protected function prepareInstance(mixed $object, string $normalizedClass)
    {
        if (isset($this->prepares[$normalizedClass])) {
            $callableOrMethodString = $this->prepares->get($normalizedClass);
            $executable = $this->executableBuilder->build($callableOrMethodString);
            $result = $executable($object, $this);
            if ($result instanceof $normalizedClass) {
                $object = $result;
            }
        }

        $interfaces = @class_implements($object);
        if ($interfaces === false) {
            throw new InjectionException(
                \sprintf(
                    "Making %s did not result in an object, instead result is of type '%s'",
                    $normalizedClass,
                    \gettype($object)
                )
            );
        }

        if ($interfaces === []) {
            return $object;
        }

        $interfaces = array_flip(array_map($this->normalizeName(...), $interfaces));
        $prepares = $this->prepares->intersect(new Map($interfaces));

        foreach ($prepares as $callableOrMethodString) {
            $executable = $this->executableBuilder->build($callableOrMethodString);
            $result = $executable($object, $this);
            if ($result instanceof $normalizedClass) {
                $object = $result;
            }
        }

        return $object;
    }

    /**
     * @throws InjectionException
     * @return mixed|object
     */
    protected function provisionInstance(string $className, string $normalizedClass, Definition $definition)
    {
        try {
            $constructor = $this->reflector->getConstructor($className);

            if (!$constructor instanceof ReflectionMethod) {
                $object = $this->instantiateWithoutConstructorParameters($className);
            } elseif (!$constructor->isPublic()) {
                throw new InjectionException(\sprintf("'%s' does not have public constructor.", $className));
            } elseif ($constructorParameters = $this->reflector->getConstructorParameters($className)) {
                $reflectionClass = $this->reflector->getClass($className);
                $classDefinition = $this->classDefinitions->get($normalizedClass);

                if ($classDefinition instanceof Definition) {
                    $definition = $definition->replace($classDefinition);
                }

                $arguments = $this->argumentsBuilder->build($constructor, $definition, $constructorParameters);
                $object = $reflectionClass->newInstanceArgs($arguments);
            } else {
                $object = $this->instantiateWithoutConstructorParameters($className);
            }
        } catch (ReflectionException) {
            throw new InjectionException('Unable to provision an instance for ' . $className);
        }

        return $object;
    }

    /**
     * @param $className
     *@throws ReflectionException
     * @throws InjectionException
     * @return mixed
     */
    protected function instantiateWithoutConstructorParameters(string $className)
    {
        $reflectionClass = $this->reflector->getClass($className);
        if (!$reflectionClass->isInstantiable()) {
            throw new InjectionException($className . ' is not instantiable');
        }

        return new $className();
    }
}

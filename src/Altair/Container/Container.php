<?php
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
use Altair\Container\Reflection\CachedReflection;
use Altair\Container\Traits\NameNormalizerTrait;
use Altair\Structure\Map;
use ReflectionException;
use ReflectionFunctionAbstract;

class Container
{
    use NameNormalizerTrait;

    /**
     * @var ReflectionInterface|CachedReflection
     */
    protected $reflector;
    /**
     * @var AliasesCollection
     */
    protected $aliases;
    /**
     * @var ClassDefinitionsCollection
     */
    protected $classDefinitions;
    /**
     * @var ParameterDefinitionsCollection
     */
    protected $parameterDefinitions;
    /**
     * @var SharesCollection
     */
    protected $shares;
    /**
     * @var PreparesCollection
     */
    protected $prepares;
    /**
     * @var DelegatesCollection
     */
    protected $delegates;
    /**
     * @var array
     */
    protected $making = [];
    /**
     * @var ExecutableBuilder|null
     */
    protected $executableBuilder;
    /**
     * @var ArgumentsBuilder|null
     */
    protected $argumentsBuilder;

    /**
     * Container constructor.
     *
     * @param ReflectionInterface|null $reflector
     * @param AliasesCollection|null $aliasesCollection
     * @param ClassDefinitionsCollection|null $classDefinitionsCollection
     * @param ParameterDefinitionsCollection|null $parameterDefinitionsCollection
     * @param SharesCollection|null $sharesCollection
     * @param PreparesCollection|null $preparesCollection
     * @param DelegatesCollection|null $delegatesCollection
     * @param ExecutableBuilder|null $executableBuilder
     * @param ArgumentsBuilder|null $argumentsBuilder
     */
    public function __construct(
        ReflectionInterface $reflector = null,
        AliasesCollection $aliasesCollection = null,
        ClassDefinitionsCollection $classDefinitionsCollection = null,
        ParameterDefinitionsCollection $parameterDefinitionsCollection = null,
        SharesCollection $sharesCollection = null,
        PreparesCollection $preparesCollection = null,
        DelegatesCollection $delegatesCollection = null,
        ExecutableBuilder $executableBuilder = null,
        ArgumentsBuilder $argumentsBuilder = null
    ) {
        $this->reflector = $reflector?? new CachedReflection();
        $this->aliases = $aliasesCollection?? new AliasesCollection();
        $this->classDefinitions = $classDefinitionsCollection?? new ClassDefinitionsCollection();
        $this->parameterDefinitions = $parameterDefinitionsCollection?? new ParameterDefinitionsCollection();
        $this->shares = $sharesCollection?? new SharesCollection();
        $this->prepares = $preparesCollection?? new PreparesCollection();
        $this->delegates = $delegatesCollection?? new DelegatesCollection();
        $this->executableBuilder = $executableBuilder?? new ExecutableBuilder($this);
        $this->argumentsBuilder = $argumentsBuilder?? new ArgumentsBuilder($this);
    }

    /**
     * @inheritdoc
     */
    public function __clone()
    {
        $this->making = [];
    }

    /**
     * Define instantiation directives for the specified class
     *
     * @param string $name The class (or alias) whose constructor arguments we wish to define
     * @param Definition $definition A definition class that holds map of  values/instructions
     *
     * @return self
     */
    public function define($name, Definition $definition): Container
    {
        list(, $normalizedClass) = $this->aliases->resolve($name);
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
     *
     * @return self
     */
    public function defineParameter($paramName, $value): Container
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
     * @return self
     */
    public function alias(string $original, string $alias)
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
     * @return self
     */
    public function share($nameOrInstance): Container
    {
        if (is_string($nameOrInstance)) {
            $this->shares->shareClass($nameOrInstance, $this->aliases);
        } elseif (is_object($nameOrInstance)) {
            $this->shares->shareInstance($nameOrInstance, $this->aliases);
        } else {
            throw new InvalidArgumentException(
                sprintf(
                    '%s::share() requires a string class name or object instance at Argument 1; %s specified',
                    __CLASS__,
                    gettype($nameOrInstance)
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
     * @param string $name
     * @param mixed $callableOrMethodStr Any callable or provisionable invokable method
     *
     * @throws InvalidArgumentException if $callableOrMethodStr is not a callable.
     *
     * @return self
     */
    public function prepare(string $name, $callableOrMethodStr): Container
    {
        if ($this->executableBuilder->isExecutable($callableOrMethodStr) === false) {
            throw new InvalidArgumentException('Invalid invokable: callable or provisional string required');
        }
        list(, $normalizedClass) = $this->aliases->resolve($name);
        $this->prepares[$normalizedClass] = $callableOrMethodStr;

        return $this;
    }

    /**
     * Delegate the creation of $name instances to the specified callable
     *
     * @param string $name
     * @param mixed $callableOrMethodStr Any callable or provisionable invokable method
     *
     * @throws InvalidArgumentException if $callableOrMethodStr is not a callable.
     * @return self
     */
    public function delegate(string $name, $callableOrMethodStr): Container
    {
        if ($this->executableBuilder->isExecutable($callableOrMethodStr) === false) {
            $errorDetail = '';
            if (is_string($callableOrMethodStr)) {
                $errorDetail = " but received '$callableOrMethodStr'";
            } elseif (is_array($callableOrMethodStr) &&
                count($callableOrMethodStr) === 2 &&
                array_key_exists(0, $callableOrMethodStr) &&
                array_key_exists(1, $callableOrMethodStr)
            ) {
                if (is_string($callableOrMethodStr[0]) && is_string($callableOrMethodStr[1])) {
                    $errorDetail = " but received ['" . $callableOrMethodStr[0] . "', '" . $callableOrMethodStr[1] . "']";
                }
            }
            throw new InvalidArgumentException(
                sprintf(
                    '%s::delegate expects a valid callable or executable class::method string at Argument 2%s',
                    __CLASS__,
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
     *
     * @param string $name
     * @param Definition|null $definition
     *
     * @return mixed|null|object
     * @throws InjectionException
     */
    public function make(string $name, Definition $definition = null)
    {
        list($className, $normalizedClass) = $this->aliases->resolve($name);

        if (isset($this->making[$normalizedClass])) {
            throw new InjectionException(
                sprintf(
                    "Cyclic dependency detected while provisioning '%s'",
                    $className
                )
            );
        }
        $definition = $definition?? new Definition([]);

        $this->making[$normalizedClass] = count($this->making);

        if (isset($this->shares[$normalizedClass])) {
            unset($this->making[$normalizedClass]);

            return $this->shares->get($normalizedClass);
        }

        if (isset($this->delegates[$normalizedClass])) {
            $executable = $this->executableBuilder->build($this->delegates->get($normalizedClass));
            $reflectionFunction = $executable->getCallableReflection();
            $arguments = $this->provisionFunctionArguments($reflectionFunction, $definition);
            $object = call_user_func_array([$executable, '__invoke'], $arguments);
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
     * @param mixed $callableOrMethodString A valid PHP callable or a provisionable ClassName::methodName string
     * @param Definition $definition Optional definition specifying params with which to invoke the provisioned callable
     *
     * @throws InjectionException
     * @return mixed Returns the invocation result returned from calling the generated executable
     */
    public function execute($callableOrMethodString, Definition $definition = null)
    {
        $executable = $this->executableBuilder->build($callableOrMethodString);
        $definition = $definition?? new Definition([]);
        $arguments = $this->provisionFunctionArguments($executable->getCallableReflection(), $definition);

        return call_user_func_array([$executable, '__invoke'], $arguments);
    }

    /**
     * @return ReflectionInterface
     */
    public function getReflector(): ReflectionInterface
    {
        return $this->reflector;
    }

    /**
     * @return AliasesCollection
     */
    public function getAliases(): AliasesCollection
    {
        return $this->aliases;
    }

    /**
     * @return ClassDefinitionsCollection
     */
    public function getClassDefinitions(): ClassDefinitionsCollection
    {
        return $this->classDefinitions;
    }

    /**
     * @return ParameterDefinitionsCollection
     */
    public function getParameterDefinitions(): ParameterDefinitionsCollection
    {
        return $this->parameterDefinitions;
    }

    /**
     * @return SharesCollection
     */
    public function getShares(): SharesCollection
    {
        return $this->shares;
    }

    /**
     * @return DelegatesCollection
     */
    public function getDelegates(): DelegatesCollection
    {
        return $this->delegates;
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function isset($name): bool
    {
        $name = $this->normalizeName($name);

        return isset($this->aliases[$name]) ||
            isset($this->delegates[$name]) ||
            isset($this->shares[$name]);
    }

    /**
     * @return ExecutableBuilder
     */
    public function getExecutableBuilder(): ExecutableBuilder
    {
        return $this->executableBuilder;
    }

    /**
     * @return ArgumentsBuilder
     */
    public function getArgumentsBuilder(): ArgumentsBuilder
    {
        return $this->argumentsBuilder;
    }

    /**
     * @param $object
     * @param $normalizedClass
     *
     * @return mixed
     * @throws InjectionException
     */
    protected function prepareInstance($object, $normalizedClass)
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
                sprintf(
                    "Making %s did not result in an object, instead result is of type '%s'",
                    $normalizedClass,
                    gettype($object)
                )
            );
        }
        if (empty($interfaces)) {
            return $object;
        }
        $interfaces = array_flip(array_map([$this, 'normalizeName'], $interfaces));
        $prepares = $this->prepares->intersect(new Map($interfaces));

        foreach ($prepares as $interfaceName => $callableOrMethodString) {
            $executable = $this->executableBuilder->build($callableOrMethodString);
            $result = $executable($object, $this);
            if ($result instanceof $normalizedClass) {
                $object = $result;
            }
        }

        return $object;
    }

    /**
     * @param $className
     * @param $normalizedClass
     * @param Definition $definition
     *
     * @return mixed|object
     * @throws InjectionException
     */
    protected function provisionInstance($className, $normalizedClass, Definition $definition)
    {
        try {
            $constructor = $this->reflector->getConstructor($className);

            if (!$constructor) {
                $object = $this->instantiateWithoutConstructorParameters($className);
            } elseif (!$constructor->isPublic()) {
                throw new InjectionException("'$className' does not have public constructor.");
            } elseif ($constructorParameters = $this->reflector->getConstructorParameters($className)) {
                $reflectionClass = $this->reflector->getClass($className);
                $definition = isset($this->classDefinitions[$normalizedClass])
                    ? $definition->replace($this->classDefinitions->get($normalizedClass))
                    : $definition;
                $arguments = $this->provisionFunctionArguments($constructor, $definition, $constructorParameters);
                $object = $reflectionClass->newInstanceArgs($arguments);
            } else {
                $object = $this->instantiateWithoutConstructorParameters($className);
            }
        } catch (ReflectionException $e) {
            throw new InjectionException('Unable to provision an instance for ' . $className);
        }

        return $object;
    }

    /**
     * @param $className
     *
     * @return mixed
     * @throws InjectionException
     */
    protected function instantiateWithoutConstructorParameters($className)
    {
        $reflectionClass = $this->reflector->getClass($className);
        if (!$reflectionClass->isInstantiable()) {
            throw new InjectionException($className . ' is not instantiable');
        }

        return new $className;
    }

    /**
     * @param ReflectionFunctionAbstract $reflectionFunction
     * @param Definition $definition
     * @param array|null $reflectionParameters
     *
     * @return array
     */
    protected function provisionFunctionArguments(
        ReflectionFunctionAbstract $reflectionFunction,
        Definition $definition,
        array $reflectionParameters = null
    ): array {
        return $this->argumentsBuilder->build($reflectionFunction, $definition, $reflectionParameters);
    }
}

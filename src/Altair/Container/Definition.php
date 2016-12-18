<?php
namespace Altair\Container;


use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;

class Definition
{
    const RAW_PREFIX = ':';
    const DELEGATE_PREFIX = '+';
    const DEFINITION_PREFIX = '@';

    protected $arguments = [];

    public function __construct(array $arguments)
    {
        $this->arguments = $arguments;
    }

    public function replace(Definition $definition): Definition
    {
        return new static(array_replace($definition->getArguments(), $this->arguments));
    }

    public function getArguments()
    {
        return $this->arguments;
    }

    public function add(string $key, $value): Definition
    {
        $this->arguments[$key] = $value;

        return $this;
    }

    public function addRaw(string $key, $value)
    {
        return $this->add(self::RAW_PREFIX . $key, $value);
    }

    public function addDelegate(string $key, $value)
    {
        return $this->add(self::DELEGATE_PREFIX . $key, $value);
    }

    public function hasIndex(int $position): bool
    {
        return isset($this->arguments[$position]) || array_key_exists($position, $this->arguments);
    }

    public function has($name): bool
    {
        return isset($this->arguments[$name]) || array_key_exists($name, $this->arguments);
    }

    public function hasRaw($name): bool
    {
        return $this->has(self::RAW_PREFIX . $name);
    }

    public function hasDelegate($name): bool
    {
        return $this->has(self::DELEGATE_PREFIX . $name);
    }

    public function hasClassDefinition($name): bool
    {
        return $this->has(self::DEFINITION_PREFIX . $name);
    }

    public function getIndexed($position)
    {
        return $this->arguments[$position];
    }

    public function get($name)
    {
        if (!isset($this->arguments[$name])) {
            throw new OutOfBoundsException("'$name' not found in definition.");
        }

        return $this->arguments[$name];
    }

    public function getRaw($name)
    {
        return $this->get(static::RAW_PREFIX . $name);
    }

    public function getDelegate($name)
    {
        return $this->get(static::DELEGATE_PREFIX . $name);
    }

    public function getClassDefinition($name)
    {
        return $this->get(static::DEFINITION_PREFIX . $name);
    }

}

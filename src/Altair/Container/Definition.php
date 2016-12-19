<?php
namespace Altair\Container;

use Altair\Container\Exception\OutOfBoundsException;

class Definition
{
    const RAW_PREFIX = ':';
    const DELEGATE_PREFIX = '+';
    const DEFINITION_PREFIX = '@';

    /**
     * @var array
     */
    protected $arguments = [];

    /**
     * Definition constructor.
     *
     * @param array $arguments
     */
    public function __construct(array $arguments)
    {
        $this->arguments = $arguments;
    }

    /**
     * @param Definition $definition
     *
     * @return Definition
     */
    public function replace(Definition $definition): Definition
    {
        return new static(array_replace($definition->getArguments(), $this->arguments));
    }

    /**
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @param string $key
     * @param $value
     *
     * @return Definition
     */
    public function add(string $key, $value): Definition
    {
        $this->arguments[$key] = $value;

        return $this;
    }

    /**
     * @param string $key
     * @param $value
     *
     * @return Definition
     */
    public function addRaw(string $key, $value)
    {
        return $this->add(self::RAW_PREFIX . $key, $value);
    }

    /**
     * @param string $key
     * @param $value
     *
     * @return Definition
     */
    public function addDelegate(string $key, $value)
    {
        return $this->add(self::DELEGATE_PREFIX . $key, $value);
    }

    /**
     * @param int $position
     *
     * @return bool
     */
    public function hasIndex(int $position): bool
    {
        return isset($this->arguments[$position]) || array_key_exists($position, $this->arguments);
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function has($name): bool
    {
        return isset($this->arguments[$name]) || array_key_exists($name, $this->arguments);
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function hasRaw($name): bool
    {
        return $this->has(self::RAW_PREFIX . $name);
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function hasDelegate($name): bool
    {
        return $this->has(self::DELEGATE_PREFIX . $name);
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function hasClassDefinition($name): bool
    {
        return $this->has(self::DEFINITION_PREFIX . $name);
    }

    /**
     * @param $position
     *
     * @return mixed
     */
    public function getIndexed($position)
    {
        return $this->arguments[$position];
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    public function get($name)
    {
        if (!isset($this->arguments[$name]) && !array_key_exists($name, $this->arguments)) {
            throw new OutOfBoundsException("'$name' not found in definition.");
        }

        return $this->arguments[$name];
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    public function getRaw($name)
    {
        return $this->get(static::RAW_PREFIX . $name);
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    public function getDelegate($name)
    {
        return $this->get(static::DELEGATE_PREFIX . $name);
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    public function getClassDefinition($name)
    {
        return $this->get(static::DEFINITION_PREFIX . $name);
    }
}

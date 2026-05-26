<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container;

use Altair\Container\Exception\OutOfBoundsException;

class Definition
{
    public const RAW_PREFIX = ':';

    public const DELEGATE_PREFIX = '+';

    public const DEFINITION_PREFIX = '@';

    /**
     * Definition constructor.
     */
    public function __construct(protected array $arguments)
    {
    }


    public function replace(Definition $definition): Definition
    {
        return new static(array_replace($definition->getArguments(), $this->arguments));
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @param $value
     *
     */
    public function add(string $key, $value): Definition
    {
        $this->arguments[$key] = $value;

        return $this;
    }

    /**
     * @param $value
     *
     */
    public function addRaw(string $key, $value): Definition
    {
        return $this->add(self::RAW_PREFIX . $key, $value);
    }

    /**
     * @param $value
     *
     */
    public function addDelegate(string $key, $value): Definition
    {
        return $this->add(self::DELEGATE_PREFIX . $key, $value);
    }


    public function hasIndex(int $position): bool
    {
        return isset($this->arguments[$position]) || array_key_exists($position, $this->arguments);
    }

    /**
     * @param $name
     */
    public function has($name): bool
    {
        return isset($this->arguments[$name]) || array_key_exists($name, $this->arguments);
    }

    /**
     * @param $name
     */
    public function hasRaw(string $name): bool
    {
        return $this->has(self::RAW_PREFIX . $name);
    }

    /**
     * @param $name
     */
    public function hasDelegate(string $name): bool
    {
        return $this->has(self::DELEGATE_PREFIX . $name);
    }

    /**
     * @param $name
     */
    public function hasClassDefinition(string $name): bool
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
        if (!array_key_exists($name, $this->arguments) && !isset($this->arguments[$name])) {
            throw new OutOfBoundsException(sprintf("'%s' not found in definition.", $name));
        }

        return $this->arguments[$name];
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    public function getRaw(string $name)
    {
        return $this->get(static::RAW_PREFIX . $name);
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    public function getDelegate(string $name)
    {
        return $this->get(static::DELEGATE_PREFIX . $name);
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    public function getClassDefinition(string $name)
    {
        return $this->get(static::DEFINITION_PREFIX . $name);
    }
}

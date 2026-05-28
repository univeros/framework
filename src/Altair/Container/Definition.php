<?php

declare(strict_types=1);

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
     *
     * @param array<int|string, mixed> $arguments
     */
    public function __construct(protected array $arguments) {}

    public function replace(Definition $definition): self
    {
        return new self(array_replace($definition->getArguments(), $this->arguments));
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function add(string $key, mixed $value): Definition
    {
        $this->arguments[$key] = $value;

        return $this;
    }

    public function addRaw(string $key, mixed $value): Definition
    {
        return $this->add(self::RAW_PREFIX . $key, $value);
    }

    public function addDelegate(string $key, mixed $value): Definition
    {
        return $this->add(self::DELEGATE_PREFIX . $key, $value);
    }

    public function hasIndex(int $position): bool
    {
        return isset($this->arguments[$position]) || \array_key_exists($position, $this->arguments);
    }

    public function has(string $name): bool
    {
        return isset($this->arguments[$name]) || \array_key_exists($name, $this->arguments);
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
     * @return mixed
     */
    public function getIndexed(int $position)
    {
        return $this->arguments[$position];
    }

    /**
     * @return mixed
     */
    public function get(string $name)
    {
        if (!\array_key_exists($name, $this->arguments) && !isset($this->arguments[$name])) {
            throw new OutOfBoundsException(\sprintf("'%s' not found in definition.", $name));
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

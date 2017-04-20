<?php

namespace Altair\Common\Contracts;

interface RegistryInterface
{
    /**
     * Returns a configuration key from the settings or $default if key is not found.
     *
     * @param string $key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * Sets a value into the registry
     *
     * @param string $key
     * @param mixed $value
     *
     * @return static
     */
    public function set(string $key, $value);
}

<?php
namespace Altair\Configuration\Support;

class Env
{
    /**
     * Returns a specific environment value.
     *
     * @param string $name the environment value name
     * @param mixed $default
     *
     * @return null|string|mixed
     */
    public function get($name, $default = null)
    {
        switch (true) {
            case array_key_exists($name, $_ENV):
                $value = $_ENV[$name];
                break;
            case array_key_exists($name, $_SERVER):
                $value = $_SERVER[$name];
                break;
            default:
                $value = getenv($name);
        }

        return $value === false ? $default : $value;
    }
}

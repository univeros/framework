<?php
namespace Altair\Configuration\Support;

class Env
{
    /**
     * Returns a specific environment value.
     *
     * @param string $name the environment value name
     *
     * @return null|string
     */
    public function get($name):? string
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

        return $value === false ? null : $value;
    }
}

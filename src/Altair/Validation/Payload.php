<?php
namespace Altair\Validation;

use Altair\Validation\Contracts\PayloadInterface;

class Payload implements PayloadInterface
{
    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * Payload constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * @param string $name
     * @param null $default
     *
     * @return mixed|null
     */
    public function getAttribute($name, $default = null)
    {
        return $this->attributes[$name]?? $default;
    }

    /**
     * @param string $name
     * @param mixed $value
     *
     * @return Payload
     */
    public function withAttribute($name, $value)
    {
        $cloned = clone $this;
        $cloned->attributes[$name] = $value;

        return $cloned;
    }

    /**
     * @param string $name
     *
     * @return Payload
     */
    public function withoutAttribute($name)
    {
        $cloned = clone $this;
        unset($cloned[$name]);

        return $cloned;
    }
}

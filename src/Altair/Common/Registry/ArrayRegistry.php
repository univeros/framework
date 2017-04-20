<?php

namespace Altair\Common\Registry;

use Altair\Common\Contracts\RegistryInterface;
use Altair\Common\Support\Arr;

class ArrayRegistry implements RegistryInterface
{
    protected $data;

    /**
     * ArrayRegistry constructor.
     *
     * @param array|null $data
     */
    public function __construct(array $data = null)
    {
        $this->data = isset($data) ? $data : [];
    }

    /**
     * @inheritdoc
     */
    public function get(string $key, $default = null)
    {
        return Arr::getValue($this->data, $key, $default);
    }

    /**
     * @inheritdoc
     */
    public function set(string $key, $value)
    {
        $this->data[$key] = $value;

        return $this;
    }
}

<?php

namespace Altair\Data\Traits;

use Altair\Data\Contracts\ArrayableInterface;

trait SerializeAwareTrait
{
    /**
     * @see ArrayableInterface::toArray()
     *
     * @return array
     */
    abstract public function toArray(): array;

    /**
     * @param array $data
     *
     * @see AttributesAwareTrait::withData()
     *
     * @return mixed
     */
    abstract public function withData(array $data);

    /**
     * @inheritDoc
     */
    public function serialize()
    {
        return serialize($this->toArray());
    }

    /**
     * @see AttributesAwareTrait::withData($data)
     *
     * @inheritDoc
     */
    public function unserialize($data)
    {
        foreach (unserialize($data) as $key => $value) {
            $this->{$key} = $value;
        }
    }
}

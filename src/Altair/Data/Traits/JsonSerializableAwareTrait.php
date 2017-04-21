<?php
namespace Altair\Data\Traits;

use Altair\Data\Contracts\ArrayableInterface;

trait JsonSerializableAwareTrait
{
    /**
     * @see ArrayableInterface::toArray()
     *
     * @return array
     */
    abstract public function toArray(): array;

    /**
     * @see ArrayableInterface::toArray()
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}

<?php
namespace Altair\Data\Traits;

trait JsonSerializableAwareTrait /* implements JsonSerializable */
{
    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}

<?php

namespace Altair\Data\Traits;

use Carbon\Carbon;

trait DateAttributeMutatorAwareTrait
{
    /**
     * Converts an attribute with date string into a Carbon instance.
     *
     * @param string $key
     *
     * @return Carbon
     */
    public function asCarbonDate(string $key): Carbon
    {
        return new Carbon($this->{$key});
    }

    /**
     * Returns a storage-friendly date string of a property.
     *
     * @param string $key
     * @param string $format accepted by http://php.net/manual/en/function.date.php
     *
     * @return string
     */
    public function asDateString(string $key, $format = 'r'): string
    {
        return $this->asCarbonDate($key)->format($format);
    }
}

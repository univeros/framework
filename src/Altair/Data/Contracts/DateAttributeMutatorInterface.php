<?php

namespace Altair\Data\Contracts;

use Carbon\Carbon;

interface DateAttributeMutatorInterface
{
    /**
     * Converts an attribute with date string into a Carbon instance.
     *
     * @param string $key
     *
     * @return Carbon
     */
    public function asCarbonDate(string $key): Carbon;

    /**
     * Returns a storage-friendly date string of a property.
     *
     * @param string $key
     * @param string $format accepted by http://php.net/manual/en/function.date.php
     *
     * @return string
     */
    public function asDateString(string $key, $format = 'r'): string;
}

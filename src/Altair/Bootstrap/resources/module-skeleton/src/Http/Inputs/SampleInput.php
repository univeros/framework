<?php

declare(strict_types=1);

namespace VendorModule\Http\Inputs;

/**
 * Input DTO for GET /sample. The endpoint takes no input, so this is empty —
 * add typed, readonly properties (and rules) as your endpoint grows.
 */
final readonly class SampleInput
{
    public function __construct() {}

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [];
    }
}

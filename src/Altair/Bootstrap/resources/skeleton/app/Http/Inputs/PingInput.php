<?php

declare(strict_types=1);

namespace App\Http\Inputs;

/**
 * Input DTO for GET /ping. The endpoint takes no input, so this is empty —
 * scaffolded endpoints with input fields get typed, readonly properties here.
 */
final readonly class PingInput
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

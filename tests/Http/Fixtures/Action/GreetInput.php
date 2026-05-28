<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Fixtures\Action;

/**
 * A spec-scaffold-shaped typed input DTO: native-typed readonly props + a
 * static rules() method. `name` is required (no default), `times` is optional.
 */
final readonly class GreetInput
{
    public function __construct(
        public string $name,
        public int $times = 1,
    ) {}

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return ['name' => ['required']];
    }
}

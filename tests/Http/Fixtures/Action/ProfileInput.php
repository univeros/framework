<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Fixtures\Action;

/**
 * Exercises the hydrator's coercion + default + nullable handling.
 */
final readonly class ProfileInput
{
    public function __construct(
        public string $email,
        public int $age = 0,
        public bool $active = false,
        public ?string $note = null,
    ) {}
}

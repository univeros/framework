<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Fixtures\Action;

/**
 * A DTO with a backed-enum field — exercises the hydrator's enum resolution.
 */
final readonly class StatusInput
{
    public function __construct(
        public Status $status,
    ) {}
}

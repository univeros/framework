<?php

declare(strict_types=1);

namespace Altair\Tests\Persistence\Cycle\Fixture;

/**
 * Minimal entity used for in-memory SQLite round-trip tests of
 * {@see \Altair\Persistence\Cycle\CycleRepository}.
 */
final class Widget
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}

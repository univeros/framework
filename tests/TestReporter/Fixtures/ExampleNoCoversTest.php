<?php

declare(strict_types=1);

namespace Altair\Tests\TestReporter\Fixtures;

use PHPUnit\Framework\TestCase;

/**
 * Fixture without any `#[CoversClass]` / `@covers` — exercises the
 * namespace-heuristic fallback.
 */
final class ExampleNoCoversTest extends TestCase
{
    public function testCompute(): void
    {
        $this->assertTrue(true);
    }
}

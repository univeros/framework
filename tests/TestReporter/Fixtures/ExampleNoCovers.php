<?php

declare(strict_types=1);

namespace Altair\Tests\TestReporter\Fixtures;

/**
 * Sibling class used by the namespace-heuristic fixture test.
 */
class ExampleNoCovers
{
    public function compute(int $x): int
    {
        return $x * 2;
    }
}

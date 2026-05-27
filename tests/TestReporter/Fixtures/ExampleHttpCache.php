<?php

declare(strict_types=1);

namespace Altair\Tests\TestReporter\Fixtures;

/**
 * Production-source fixture for SourceUnderTestResolver tests.
 *
 * Exercises the namespace heuristic: a test class at
 * `Altair\Tests\TestReporter\Fixtures\ExampleHttpCacheTest` should
 * resolve to this class by stripping the `Tests\` segment and the
 * trailing `Test` suffix.
 */
class ExampleHttpCache
{
    public function isCacheable(int $maxAge): bool
    {
        return $maxAge > 0;
    }
}

<?php

declare(strict_types=1);

namespace Altair\Tests\TestReporter\Fixtures;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Fixture test class used by SourceUnderTestResolverTest to exercise
 * the `#[CoversClass]` signal. Not auto-discovered by the framework
 * suite (the parent test instantiates it explicitly via reflection).
 *
 * Methods are deliberately empty — we test the RESOLVER's mapping, not
 * the production class.
 */
#[CoversClass(ExampleHttpCache::class)]
final class ExampleHttpCacheTest extends TestCase
{
    public function testIsCacheableReturnsTrueWithMaxAge(): void
    {
        $this->assertTrue(true);
    }

    public function testNonExistentMethod(): void
    {
        $this->assertTrue(true);
    }
}

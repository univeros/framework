<?php

declare(strict_types=1);

namespace Altair\Tests\TestReporter\Fixtures;

use PHPUnit\Framework\TestCase;

/**
 * Fixture exercising the legacy `@covers` annotation path.
 *
 * @covers \Altair\Tests\TestReporter\Fixtures\ExampleHttpCache
 */
final class LegacyCoversAnnotationTest extends TestCase
{
    public function testAnything(): void
    {
        $this->assertTrue(true);
    }
}

<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/*
 * Runtime path-enumeration fixture for AgentSpec's TestFixtureScanner.
 *
 * Declared as a concrete TestCase with a trivial passing assertion so
 * PHPUnit's discovery accepts it without runner warnings. It is NOT a
 * real test — only its file path is consumed by the scanner.
 */
final class SampleGreeterTest extends TestCase
{
    public function testFixturePlaceholder(): void
    {
        self::assertTrue(true);
    }
}

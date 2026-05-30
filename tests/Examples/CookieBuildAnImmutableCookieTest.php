<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Examples;

use Altair\Cookie\Cookie;
use PHPUnit\Framework\TestCase;

/**
 * Runs the snippet from .altair/examples/cookie/build-an-immutable-cookie.md
 * end-to-end. Asserts withValue() returns a fresh instance and that the
 * stringify form matches the wire format.
 */
final class CookieBuildAnImmutableCookieTest extends TestCase
{
    public function testStringifyMatchesWireFormat(): void
    {
        $session = new Cookie('session', 'abc123');

        self::assertSame('session=abc123', (string) $session);
    }

    public function testWithValueReturnsNewInstanceLeavingOriginalUntouched(): void
    {
        $session = new Cookie('session', 'abc123');
        $rotated = $session->withValue('def456');

        self::assertNotSame($session, $rotated);
        self::assertSame('session=abc123', (string) $session);
        self::assertSame('session=def456', (string) $rotated);
    }

    public function testNullValueRendersAsEmptyValue(): void
    {
        $cleared = (new Cookie('session', 'abc123'))->withValue(null);

        self::assertSame('session=', (string) $cleared);
    }
}

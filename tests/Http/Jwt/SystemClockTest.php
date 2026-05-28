<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Http\Jwt;

use Altair\Http\Jwt\SystemClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

#[CoversClass(SystemClock::class)]
final class SystemClockTest extends TestCase
{
    public function testNowReturnsCurrentInstant(): void
    {
        $clock = new SystemClock();

        self::assertInstanceOf(ClockInterface::class, $clock);

        $before = time();
        $now = $clock->now()->getTimestamp();
        $after = time();

        self::assertGreaterThanOrEqual($before, $now);
        self::assertLessThanOrEqual($after, $now);
    }
}

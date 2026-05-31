<?php

declare(strict_types=1);

namespace Altair\Tests\Idempotency\Hash;

use Altair\Idempotency\Exception\IdempotencyException;
use Altair\Idempotency\Hash\TtlParser;
use PHPUnit\Framework\TestCase;

final class TtlParserTest extends TestCase
{
    public function testSecondsUnit(): void
    {
        self::assertSame(30, (new TtlParser())->toSeconds('30s'));
    }

    public function testMinutesUnit(): void
    {
        self::assertSame(300, (new TtlParser())->toSeconds('5m'));
    }

    public function testHoursUnit(): void
    {
        self::assertSame(86_400, (new TtlParser())->toSeconds('24h'));
    }

    public function testDaysUnit(): void
    {
        self::assertSame(7 * 86_400, (new TtlParser())->toSeconds('7d'));
    }

    public function testMillisecondsRoundUpToOneSecond(): void
    {
        // Sub-second TTLs are accepted by the spec validator but storage
        // adapters work in seconds; round up so a small positive TTL never
        // collapses to zero.
        self::assertSame(1, (new TtlParser())->toSeconds('500ms'));
        self::assertSame(1, (new TtlParser())->toSeconds('999ms'));
    }

    public function testMillisecondsAboveOneSecondRoundUp(): void
    {
        self::assertSame(2, (new TtlParser())->toSeconds('1500ms'));
        self::assertSame(3, (new TtlParser())->toSeconds('2001ms'));
    }

    public function testZeroMillisecondsCollapsesToZero(): void
    {
        self::assertSame(0, (new TtlParser())->toSeconds('0ms'));
    }

    public function testMalformedRejected(): void
    {
        $this->expectException(IdempotencyException::class);
        $this->expectExceptionMessage('must match');
        (new TtlParser())->toSeconds('forever');
    }

    public function testUnknownUnitRejected(): void
    {
        $this->expectException(IdempotencyException::class);
        (new TtlParser())->toSeconds('5y');
    }

    public function testNegativeNumberRejected(): void
    {
        $this->expectException(IdempotencyException::class);
        (new TtlParser())->toSeconds('-1h');
    }

    public function testZeroSecondsAccepted(): void
    {
        // Zero seconds is degenerate but valid; storage adapters treat it
        // as "expire immediately" which is a reasonable interpretation.
        self::assertSame(0, (new TtlParser())->toSeconds('0s'));
    }
}

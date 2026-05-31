<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Dispatcher;

use Altair\Webhooks\Dispatcher\RetryPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetryPolicy::class)]
final class RetryPolicyTest extends TestCase
{
    public function testDefaults(): void
    {
        $policy = new RetryPolicy();

        self::assertSame(5, $policy->maxAttempts);
        self::assertSame(RetryPolicy::EXPONENTIAL, $policy->backoff);
        self::assertSame(30, $policy->baseDelaySeconds);
    }

    #[DataProvider('exponentialCases')]
    public function testExponentialBackoff(int $attempt, int $expected): void
    {
        $policy = new RetryPolicy(baseDelaySeconds: 30, backoff: RetryPolicy::EXPONENTIAL);

        self::assertSame($expected, $policy->delayFor($attempt));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function exponentialCases(): iterable
    {
        yield 'attempt 1' => [1, 30];
        yield 'attempt 2' => [2, 60];
        yield 'attempt 3' => [3, 120];
        yield 'attempt 4' => [4, 240];
    }

    public function testLinearBackoff(): void
    {
        $policy = new RetryPolicy(baseDelaySeconds: 10, backoff: RetryPolicy::LINEAR);

        self::assertSame(10, $policy->delayFor(1));
        self::assertSame(20, $policy->delayFor(2));
        self::assertSame(30, $policy->delayFor(3));
    }

    public function testDelayForClampsAttemptToAtLeastOne(): void
    {
        $policy = new RetryPolicy(baseDelaySeconds: 30, backoff: RetryPolicy::EXPONENTIAL);

        self::assertSame(30, $policy->delayFor(0));
    }
}

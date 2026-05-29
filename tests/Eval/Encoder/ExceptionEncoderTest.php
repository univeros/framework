<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Eval\Encoder;

use Altair\Eval\Encoder\ExceptionEncoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ExceptionEncoder::class)]
final class ExceptionEncoderTest extends TestCase
{
    public function testEncodesClassMessageFileAndLine(): void
    {
        $exception = new RuntimeException('boom', 42);

        $encoded = ExceptionEncoder::encode($exception);

        self::assertSame(RuntimeException::class, $encoded['class']);
        self::assertSame('boom', $encoded['message']);
        self::assertSame(42, $encoded['code']);
        self::assertIsString($encoded['file']);
        self::assertIsInt($encoded['line']);
        self::assertIsArray($encoded['stack_trace']);
    }

    public function testStackTraceFramesAreRenderedAsCallableAndLocationStrings(): void
    {
        $exception = $this->raise();

        $frames = ExceptionEncoder::encode($exception)['stack_trace'];

        self::assertNotEmpty($frames);
        self::assertStringContainsString('raise', $frames[0]);
        self::assertStringContainsString(__FILE__, $frames[0]);
    }

    public function testPreviousChainIsWalkedAndCapped(): void
    {
        $root = new RuntimeException('root');
        $current = $root;
        for ($i = 0; $i < ExceptionEncoder::MAX_PREVIOUS_CHAIN + 5; ++$i) {
            $current = new RuntimeException('wrap-' . $i, 0, $current);
        }

        $chain = ExceptionEncoder::encode($current)['previous'];

        self::assertCount(ExceptionEncoder::MAX_PREVIOUS_CHAIN, $chain);
    }

    public function testNoPreviousChainOmitsTheKey(): void
    {
        $encoded = ExceptionEncoder::encode(new RuntimeException('alone'));

        self::assertArrayNotHasKey('previous', $encoded);
    }

    private function raise(): RuntimeException
    {
        return new RuntimeException('test');
    }
}

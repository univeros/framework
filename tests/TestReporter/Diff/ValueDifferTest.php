<?php

declare(strict_types=1);

namespace Altair\Tests\TestReporter\Diff;

use Altair\TestReporter\Diff\ValueDiffer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValueDiffer::class)]
class ValueDifferTest extends TestCase
{
    public function testScalarDiffSurfacesExpectedAndActual(): void
    {
        $diff = (new ValueDiffer())->renderDiff(42, 7);
        $this->assertSame('scalar', $diff['kind']);
        $this->assertSame(42, $diff['expected']);
        $this->assertSame(7, $diff['actual']);
    }

    public function testArrayDiffSurfacesAddedRemovedChanged(): void
    {
        $diff = (new ValueDiffer())->renderDiff(
            ['a' => 1, 'b' => 2, 'c' => 3],
            ['a' => 1, 'b' => 20, 'd' => 4],
        );

        $this->assertSame('array', $diff['kind']);
        $this->assertSame(['d' => 4], $diff['added']);
        $this->assertSame(['c' => 3], $diff['removed']);
        $this->assertSame(['b' => ['expected' => 2, 'actual' => 20]], $diff['changed']);
    }

    public function testStringDiffEmitsPreviewsAndLengths(): void
    {
        $diff = (new ValueDiffer())->renderDiff('hello', 'world');
        $this->assertSame('string', $diff['kind']);
        $this->assertSame('hello', $diff['expected_preview']);
        $this->assertSame('world', $diff['actual_preview']);
        $this->assertSame(5, $diff['expected_length']);
        $this->assertSame(5, $diff['actual_length']);
    }

    public function testLongStringIsTruncated(): void
    {
        $long = str_repeat('a', ValueDiffer::STRING_PREVIEW_LIMIT + 50);
        $diff = (new ValueDiffer())->renderDiff($long, '');
        $this->assertStringEndsWith('more chars)', $diff['expected_preview']);
        $this->assertSame(ValueDiffer::STRING_PREVIEW_LIMIT + 50, $diff['expected_length']);
    }

    public function testObjectDiffReportsClasses(): void
    {
        $expected = new class {
            public function __toString(): string
            {
                return 'expected-obj';
            }
        };
        $actual = new class {
            public function __toString(): string
            {
                return 'actual-obj';
            }
        };

        $diff = (new ValueDiffer())->renderDiff($expected, $actual);
        $this->assertSame('object', $diff['kind']);
        $this->assertSame($expected::class, $diff['expected_class']);
        $this->assertSame($actual::class, $diff['actual_class']);
        $this->assertSame('expected-obj', $diff['expected_preview']);
    }

    public function testNullComparisonFailureReturnsNull(): void
    {
        $this->assertNull((new ValueDiffer())->diff(null));
    }
}

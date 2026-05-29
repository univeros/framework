<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Eval\Encoder;

use Altair\Eval\Encoder\ValueEncoder;
use ArrayIterator;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(ValueEncoder::class)]
final class ValueEncoderTest extends TestCase
{
    public function testEncodesEveryScalarType(): void
    {
        self::assertSame(['type' => 'null', 'value' => null], ValueEncoder::encode(null));
        self::assertSame(['type' => 'bool', 'value' => true], ValueEncoder::encode(true));
        self::assertSame(['type' => 'int', 'value' => 42], ValueEncoder::encode(42));
        self::assertSame(['type' => 'float', 'value' => 3.14], ValueEncoder::encode(3.14));
        self::assertSame(['type' => 'string', 'value' => 'hi'], ValueEncoder::encode('hi'));
    }

    public function testEncodesNonFiniteFloatsAsStringSentinels(): void
    {
        self::assertSame(['type' => 'float', 'value' => 'NaN'], ValueEncoder::encode(\NAN));
        self::assertSame(['type' => 'float', 'value' => 'Infinity'], ValueEncoder::encode(\INF));
        self::assertSame(['type' => 'float', 'value' => '-Infinity'], ValueEncoder::encode(-\INF));
    }

    public function testTruncatesOverlongStringsButReportsTotalLength(): void
    {
        $long = str_repeat('a', ValueEncoder::STRING_MAX + 100);

        $encoded = ValueEncoder::encode($long);

        self::assertTrue($encoded['truncated']);
        self::assertSame(ValueEncoder::STRING_MAX + 100, $encoded['length']);
        self::assertStringEndsWith('...(truncated)', $encoded['value']);
    }

    public function testListVsAssocArraysAreFlagged(): void
    {
        $list = ValueEncoder::encode([1, 2, 3]);
        self::assertTrue($list['is_list']);
        self::assertSame(3, $list['count']);

        $assoc = ValueEncoder::encode(['a' => 1, 'b' => 2]);
        self::assertFalse($assoc['is_list']);
        self::assertSame('int', $assoc['value']['a']['type']);
    }

    public function testObjectEncodesPublicPropertiesByDefault(): void
    {
        $obj = new stdClass();
        $obj->name = 'x';
        $obj->n = 7;

        $encoded = ValueEncoder::encode($obj);

        self::assertSame('object', $encoded['type']);
        self::assertSame(stdClass::class, $encoded['class']);
        self::assertSame('x', $encoded['properties']['name']['value']);
    }

    public function testObjectPrefersDebugInfoWhenAvailable(): void
    {
        $obj = new class {
            public string $secret = 'real';

            public function __debugInfo(): array
            {
                return ['public' => 'safe'];
            }
        };

        $encoded = ValueEncoder::encode($obj);

        self::assertArrayHasKey('public', $encoded['properties']);
        self::assertArrayNotHasKey('secret', $encoded['properties']);
    }

    public function testObjectCycleEmitsReferenceShape(): void
    {
        $a = new stdClass();
        $b = new stdClass();
        $a->next = $b;
        $b->back = $a;

        $encoded = ValueEncoder::encode($a);

        $bNode = $encoded['properties']['next'];
        self::assertSame('object', $bNode['type']);
        $reference = $bNode['properties']['back'];
        self::assertSame('reference', $reference['type']);
        self::assertSame(stdClass::class, $reference['class']);
    }

    public function testDepthCapTruncatesDeeplyNestedArrays(): void
    {
        $deep = [[[[[['leaf']]]]]];

        $encoded = ValueEncoder::encode($deep);

        $cursor = $encoded;
        for ($i = 0; $i < ValueEncoder::MAX_DEPTH; ++$i) {
            self::assertSame('array', $cursor['type']);
            self::assertArrayHasKey(0, $cursor['value']);
            $cursor = $cursor['value'][0];
        }

        self::assertTrue($cursor['truncated']);
    }

    public function testIterableShowsBoundedPreviewAndExhaustedFlag(): void
    {
        $small = new ArrayIterator(['a' => 1, 'b' => 2]);

        $encoded = ValueEncoder::encode($small);

        self::assertSame('iterable', $encoded['type']);
        self::assertTrue($encoded['exhausted']);
        self::assertCount(2, $encoded['preview']);
        self::assertSame(2, $encoded['size_hint']);
        self::assertSame('a', $encoded['preview'][0]['key']['value']);
    }

    public function testInfiniteGeneratorStopsAtPreviewLimit(): void
    {
        $counter = static function (): Generator {
            $i = 0;
            while (true) {
                yield $i++;
            }
        };

        $encoded = ValueEncoder::encode($counter());

        self::assertCount(ValueEncoder::ITERABLE_PREVIEW, $encoded['preview']);
        self::assertFalse($encoded['exhausted']);
        self::assertArrayNotHasKey('size_hint', $encoded);
    }

    public function testResourceEncodesItsType(): void
    {
        $handle = fopen('php://memory', 'rb');
        self::assertIsResource($handle);

        $encoded = ValueEncoder::encode($handle);
        fclose($handle);

        self::assertSame('resource', $encoded['type']);
        self::assertSame('stream', $encoded['value']);
    }
}

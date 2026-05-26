<?php
namespace Altair\Tests\Structure\Sequence;


use PHPUnit\Framework\Attributes\DataProvider;
trait contains
{
    public static function containsDataProvider(): array
    {
        $sample = static::sample();

        // initial, values, expected
        return [
            [[1, 2, 3], [], true], // Empty set is a subset of any set

            [['a'], ['a'], true],
            [[1], [1], true],
            [[1], [], true],
            [['a'], ['b'], false],
            [[], [1], false],
            [['1'], [1], false],
            [[1], ['1'], false],

            [[], $sample, false],
            [$sample, $sample, true],

            [array_slice($sample, 1), $sample, false],
            [$sample, array_slice($sample, 1), true],
        ];
    }

    #[DataProvider('containsDataProvider')]
    public function testContains(mixed $initial, array $values, bool $expected): void
    {
        $instance = static::getInstance($initial);
        $this->assertEquals($expected, $instance->contains(...$values));
    }
}

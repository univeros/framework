<?php
namespace Altair\Tests\Structure\Sequence;


use PHPUnit\Framework\Attributes\DataProvider;
trait insert
{
    public static function insertDataProvider(): array
    {
        $s = static::sample();

        $h = count($s) / 2;

        // initial, index, values
        return [
            [[0], 0, [1]],
            [[0], 1, [1]],

            [['a', 'b'], 0, ['c', 'd']],
            [['a', 'b'], 1, ['c', 'd']],
            [['a', 'b'], 2, ['c', 'd']],

            // Inserting many values at the front of an empty sequence
            [[], 0, $s],

            // Inserting many values at the front of a non-empty sequence
            [$s, 0, $s],

            // Inserting many values at the front of a non-empty sequence
            [$s, $h, $s],
        ];
    }

    #[DataProvider('insertDataProvider')]
    public function testInsertVariadic(array $initial, mixed $index, array $values): void
    {
        $expected = $initial;
        array_splice($expected, $index, 0, $values);

        $instance = static::getInstance($initial);
        $instance->insert($index, ...$values);

        $this->assertEquals(count($expected), count($instance));
        $this->assertToArray($expected, $instance);
    }

    #[DataProvider('insertDataProvider')]
    public function testInsert(array $initial, mixed $index, array $values): void
    {
        $expected = $initial;
        array_splice($expected, $index, 0, array_reverse($values));

        $instance = static::getInstance($initial);

        foreach ($values as $value) {
            $instance->insert($index, $value);
        }

        $this->assertEquals(count($expected), count($instance));
        $this->assertToArray($expected, $instance);
    }

    #[DataProvider('outOfRangeDataProvider')]
    public function testInsertIndexOutOfRange(mixed $initial, mixed $index): void
    {
        $instance = static::getInstance($initial);
        $this->expectIndexOutOfRangeException();
        $instance->insert($index);
    }

    #[DataProvider('badIndexDataProvider')]
    public function testInsertIndexBadIndex(mixed $initial, mixed $index): void
    {
        $instance = static::getInstance();
        $this->expectWrongIndexTypeException();
        $instance->insert($index);
    }
}

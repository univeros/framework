<?php
namespace Altair\Tests\Structure\Map;


use PHPUnit\Framework\Attributes\DataProvider;
trait sort
{
    public static function sortedDataProvider(): array
    {
        return [
            [[
            ]],
            [[
                'a' => 3,
                'c' => 1,
                'b' => 2,
            ]],
            [[
                3 => 'd',
                0 => 'a',
                1 => 'b',
                4 => 'e',
                2 => 'c',
            ]],
        ];
    }

    #[DataProvider('sortedDataProvider')]
    public function testSorted(array $values): void
    {
        $instance = static::getInstance($values);

        $expected = array_slice($values, 0, count($values), true);
        asort($expected);

        $sorted = $instance->sort();
        $this->assertToArray($expected, $sorted);
        $this->assertToArray($values, $instance);
    }

    #[DataProvider('sortedDataProvider')]
    public function testSortedUsingComparator(array $values): void
    {
        $instance = static::getInstance($values);

        $sorted = $instance->sort(fn($a, $b): int => $b <=> $a);

        $expected = array_slice($values, 0, count($values), true);
        arsort($expected);

        $this->assertToArray($expected, $sorted);
        $this->assertToArray($values, $instance);
    }
}

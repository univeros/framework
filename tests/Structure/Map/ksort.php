<?php
namespace Altair\Tests\Structure\Map;


use PHPUnit\Framework\Attributes\DataProvider;
trait ksort
{
    public static function sortKeyDataProvider(): array
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

    #[DataProvider('sortKeyDataProvider')]
    public function testSortedByKey(array $values): void
    {
        $instance = static::getInstance($values);

        $expected = $values;
        ksort($expected);

        $ksorted = $instance->ksort();
        $this->assertToArray($expected, $ksorted);
        $this->assertToArray($values, $instance);
    }

    #[DataProvider('sortKeyDataProvider')]
    public function testSortedByKeyUsingComparator(array $values): void
    {
        $instance = static::getInstance($values);

        $sorted = $instance->ksort(fn($a, $b): int => $b <=> $a);

        $expected = $values;
        krsort($expected);

        $this->assertToArray($expected, $sorted);
        $this->assertToArray($values, $instance);
    }
}

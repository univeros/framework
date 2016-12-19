<?php
namespace Altair\Tests\Structure\Map;

trait sort
{
    public function sortedDataProvider()
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

    /**
     * @dataProvider sortedDataProvider
     */
    public function testSorted(array $values)
    {
        $instance = $this->getInstance($values);

        $expected = array_slice($values, 0, count($values), true);
        asort($expected);

        $sorted = $instance->sort();
        $this->assertToArray($expected, $sorted);
        $this->assertToArray($values, $instance);
    }

    /**
     * @dataProvider sortedDataProvider
     */
    public function testSortedUsingComparator(array $values)
    {
        $instance = $this->getInstance($values);

        $sorted = $instance->sort(function ($a, $b) {
            return $b <=> $a;
        });

        $expected = array_slice($values, 0, count($values), true);
        arsort($expected);

        $this->assertToArray($expected, $sorted);
        $this->assertToArray($values, $instance);
    }
}

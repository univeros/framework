<?php
namespace Altair\tests\Structure\Sequence;

trait contains
{
    public function containsDataProvider()
    {
        $sample = $this->sample();

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

    /**
     * @dataProvider containsDataProvider
     */
    public function testContains($initial, array $values, bool $expected)
    {
        $instance = $this->getInstance($initial);
        $this->assertEquals($expected, $instance->contains(...$values));
    }
}

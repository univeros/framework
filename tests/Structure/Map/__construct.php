<?php
namespace Altair\Tests\Structure\Map;

use Altair\Structure\Map;

trait __construct
{
    public function constructDataProvider()
    {
        return array_map(function ($a) {
            return [$a, $a];
        }, [
            [],
            ['a' => 1],
            ['a' => 1, 'b' => 2],
            ['a' => 1, 'b' => 2, 'c' => 3],
            $this->sample(),
        ]);
    }

    /**
     * @dataProvider constructDataProvider
     */
    public function testConstruct(array $values, array $expected)
    {
        $this->assertToArray($expected, new Map($values));
    }

    /**
     * @dataProvider constructDataProvider
     */
    public function testConstructUsingNonArrayIterable(array $values, array $expected)
    {
        $this->assertToArray($expected, new Map(new \ArrayIterator($values)));
    }

    public function testConstructNoParams()
    {
        $this->assertToArray([], new Map());
    }
}

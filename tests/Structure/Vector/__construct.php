<?php
namespace Altair\Tests\Structure\Vector;

use Altair\Structure\Vector;

trait __construct
{
    public function constructDataProvider()
    {
        return array_map(function ($a) {
            return [$a, $a];
        }, [
            [],
            ['a'],
            ['a', 'b'],
            ['a', 'b', 'c'],
            $this->sample(),
            range(1, self::MANY),
        ]);
    }

    /**
     * @dataProvider constructDataProvider
     * @param mixed $values
     */
    public function testConstruct($values, array $expected)
    {
        $this->assertToArray($expected, new Vector($values));
    }

    /**
     * @dataProvider constructDataProvider
     */
    public function testConstructUsingNonArrayIterable(array $values, array $expected)
    {
        $this->assertToArray($expected, new Vector(new \ArrayIterator($values)));
    }

    public function testConstructNoParams()
    {
        $this->assertToArray([], new Vector());
    }
}

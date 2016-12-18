<?php
namespace Altair\Tests\Structure\Set;

use Altair\Structure\Set;

trait __construct
{
    public function constructDataProvider()
    {
        list($unique, $duplicated) = $this->getUniqueAndDuplicateData();

        return [
            [[],            []],
            [['a'],         ['a']],
            [['a', 'a'],    ['a']],
            [['a', 'b'],    ['a', 'b']],
            [$unique,       $unique],
            [$duplicated,   $unique],
        ];
    }

    /**
     * @dataProvider constructDataProvider
     */
    public function testConstruct(array $values, array $expected)
    {
        $this->assertToArray($expected, new Set($values));
    }

    /**
     * @dataProvider constructDataProvider
     */
    public function testConstructUsingIterable(array $values, array $expected)
    {
        $this->assertToArray($expected, new Set(new \ArrayIterator($values)));
    }

    public function testConstructNoParams()
    {
        $this->assertToArray([], new Set());
    }
}

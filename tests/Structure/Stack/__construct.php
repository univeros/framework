<?php
namespace Altair\Tests\Structure\Stack;

use Altair\Structure\Stack;

trait __construct
{
    public function constructDataProvider()
    {
        return [
            [[]],
            [['a']],
            [['a', 'a']],
            [['a', 'b']],
            [$this->sample()],
        ];
    }

    /**
     * @dataProvider constructDataProvider
     */
    public function testConstruct(array $values)
    {
        $this->assertToArray(array_reverse($values), new Stack($values));
    }

    /**
     * @dataProvider constructDataProvider
     */
    public function testConstructUsingIterable(array $values)
    {
        $this->assertToArray(array_reverse($values), new Stack(new \ArrayIterator($values)));
    }

    public function testConstructNoParams()
    {
        $this->assertToArray([], new Stack());
    }
}

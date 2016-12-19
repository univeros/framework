<?php
namespace Altair\Tests\Structure\Sequence;

trait last
{
    public function lastDataProvider()
    {
        // initial, returned
        return [
            [['a'],             'a'],
            [['a', 'b'],        'b'],
            [['a', 'b', 'c'],   'c'],
        ];
    }

    /**
     * @dataProvider lastDataProvider
     * @param mixed $initial
     * @param mixed $expected
     */
    public function testLast($initial, $expected)
    {
        $instance = $this->getInstance($initial);
        $this->assertEquals($expected, $instance->last());
    }

    public function testLastNotAllowedWhenEmpty()
    {
        $instance = $this->getInstance();
        $this->expectEmptyNotAllowedException();
        $instance->last();
    }
}

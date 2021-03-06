<?php
namespace Altair\Tests\Structure\Sequence;

trait first
{
    public function firstDataProvider()
    {
        // initial, returned
        return [
            [['a'],             'a'],
            [['a', 'b'],        'a'],
            [['a', 'b', 'c'],   'a'],
        ];
    }

    /**
     * @dataProvider firstDataProvider
     * @param mixed $expected
     */
    public function testFirst(array $initial, $expected)
    {
        $instance = $this->getInstance($initial);
        $this->assertEquals($expected, $instance->first());
    }

    public function testFirstNowAllowedWhenEmpty()
    {
        $instance = $this->getInstance();
        $this->expectEmptyNotAllowedException();
        $instance->first();
    }
}

<?php
namespace Altair\tests\Structure\Map;

trait first
{
    public function firstDataProvider()
    {
        // initial, returned
        return [
            [['a'],             [0, 'a']],
            [['a', 'b'],        [0, 'a']],
            [['a', 'b', 'c'],   [0, 'a']],
        ];
    }

    /**
     * @dataProvider firstDataProvider
     */
    public function testFirst(array $initial, $expected)
    {
        $instance = $this->getInstance($initial);
        $first = $instance->first();

        $this->assertEquals($expected, [$first->key, $first->value]);
    }

    public function testFirstNowAllowedWhenEmpty()
    {
        $instance = $this->getInstance();
        $this->expectEmptyNotAllowedException();
        $instance->first();
    }
}

<?php
namespace Altair\Tests\Structure\Set;

trait first
{
    public static function firstDataProvider()
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
        $instance = static::getInstance($initial);
        $this->assertEquals($expected, $instance->first());
    }

    public function testFirstNowAllowedWhenEmpty()
    {
        $instance = static::getInstance();
        $this->expectEmptyNotAllowedException();
        $instance->first();
    }
}

<?php
namespace Altair\Tests\Structure\Map;

trait first
{
    public static function firstDataProvider()
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
     * @param mixed $expected
     */
    public function testFirst(array $initial, $expected)
    {
        $instance = static::getInstance($initial);
        $first = $instance->first();

        $this->assertEquals($expected, [$first->key, $first->value]);
    }

    public function testFirstNowAllowedWhenEmpty()
    {
        $instance = static::getInstance();
        $this->expectEmptyNotAllowedException();
        $instance->first();
    }
}

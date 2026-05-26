<?php
namespace Altair\Tests\Structure\Stack;

trait peek
{
    public static function peekDataProvider()
    {
        // initial, returned, expected result
        return [
            [['a'],         'a'],
            [['a', 'b'],    'b'],
        ];
    }

    /**
     * @dataProvider peekDataProvider
     * @param mixed $initial
     * @param mixed $returned
     */
    public function testPeek($initial, $returned)
    {
        $instance = static::getInstance($initial);

        $value = $instance->peek();

        $this->assertToArray(array_reverse($initial), $instance);
        $this->assertEquals($returned, $value);
    }

    public function testPeekNotAllowedWhenEmpty()
    {
        $instance = static::getInstance();
        $this->expectEmptyNotAllowedException();
        $instance->peek();
    }
}

<?php
namespace Altair\Tests\Structure\Queue;

trait peek
{
    public static function peekDataProvider()
    {
        // initial, returned, expected result
        return [
            [['a'],         'a'],
            [['a', 'b'],    'a'],
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

        $this->assertToArray($initial, $instance);
        $this->assertEquals($returned, $value);
    }

    public function testPeekNotAllowedWhenEmpty()
    {
        $instance = static::getInstance();
        $this->expectEmptyNotAllowedException();
        $instance->peek();
    }
}

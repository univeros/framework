<?php
namespace Altair\Tests\Structure\PriorityQueue;

trait peek
{
    public static function peekDataProvider()
    {
        // initial, expected
        return [
            [['a' => 1, 'b' => 2], 'b'],
            [['a' => 2, 'b' => 1], 'a'],
            [['a' => 1, 'b' => 1], 'a'],
        ];
    }

    /**
     * @dataProvider peekDataProvider
     * @param mixed $expected
     */
    public function testPeek(array $initial, $expected)
    {
        $instance = static::getInstance($initial);
        $this->assertEquals($expected, $instance->peek());
        $this->assertCount(count($initial), $instance);
    }

    public function testPeekNotAllowedWhenEmpty()
    {
        $instance = static::getInstance();
        $this->expectEmptyNotAllowedException();
        $instance->peek();
    }
}

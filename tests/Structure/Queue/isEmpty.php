<?php
namespace Altair\Tests\Structure\Queue;

trait isEmpty
{
    public static function isEmptyDataProvider()
    {
        // values, is empty
        return [
            [[],         true],
            [['a'],     false],
        ];
    }

    /**
     * @dataProvider isEmptyDataProvider
     */
    public function testIsEmpty(array $values, bool $isEmpty)
    {
        $instance = static::getInstance($values);
        $this->assertEquals($isEmpty, $instance->isEmpty());
    }

    public function testIsNotEmptyAfterPop()
    {
        $instance = static::getInstance();
        $this->assertTrue($instance->isEmpty());

        $instance->push('a');
        $this->assertFalse($instance->isEmpty());

        $instance->pop();
        $this->assertTrue($instance->isEmpty());
    }
}

<?php
namespace Altair\Tests\Structure\Map;

trait isEmpty
{
    public static function isEmptyDataProvider(): array
    {
        // values, is empty
        return [
            [[],              true],
            [['a' => 1],     false],
        ];
    }

    /**
     * @dataProvider isEmptyDataProvider
     */
    public function testIsEmpty(array $values, bool $isEmpty): void
    {
        $instance = static::getInstance($values);
        $this->assertEquals($isEmpty, $instance->isEmpty());
    }

    public function testIsNotEmptyAfterRemove(): void
    {
        $instance = static::getInstance();
        $this->assertTrue($instance->isEmpty());

        $instance->put('a', 1);
        $this->assertFalse($instance->isEmpty());

        $instance->remove('a');
        $this->assertTrue($instance->isEmpty());
    }
}

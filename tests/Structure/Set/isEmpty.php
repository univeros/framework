<?php
namespace Altair\Tests\Structure\Set;

trait isEmpty
{
    public static function isEmptyDataProvider(): array
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
    public function testIsEmpty(array $values, bool $isEmpty): void
    {
        $instance = static::getInstance($values);
        $this->assertEquals($isEmpty, $instance->isEmpty());
    }

    public function testIsNotEmptyAfterRemove(): void
    {
        $instance = static::getInstance();
        $this->assertTrue($instance->isEmpty());

        $instance->add('a');
        $this->assertFalse($instance->isEmpty());

        $instance->remove('a');
        $this->assertTrue($instance->isEmpty());
    }
}

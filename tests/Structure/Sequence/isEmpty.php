<?php
namespace Altair\Tests\Structure\Sequence;


use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('isEmptyDataProvider')]
    public function testIsEmpty(array $values, bool $isEmpty): void
    {
        $instance = static::getInstance($values);
        $this->assertEquals($isEmpty, $instance->isEmpty());
    }

    public function testIsNotEmptyAfterRemove(): void
    {
        $instance = static::getInstance();
        $this->assertTrue($instance->isEmpty());

        $instance->push('a');
        $this->assertFalse($instance->isEmpty());

        $instance->remove(0);
        $this->assertTrue($instance->isEmpty());
    }
}

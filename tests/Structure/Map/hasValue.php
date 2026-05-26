<?php
namespace Altair\Tests\Structure\Map;


use PHPUnit\Framework\Attributes\DataProvider;
trait hasValue
{
    public static function hasValueDataProvider(): array
    {
        // initial, value, expected
        return [
            [[],                1,      false],
            [['a' => 1],        1,      true],
            [['a' => 1],        2,      false],
            [['a' => null],     null,   true],
        ];
    }

    #[DataProvider('hasValueDataProvider')]
    public function testHasValue(array $initial, mixed $value, bool $expected): void
    {
        $instance = static::getInstance($initial);
        $this->assertEquals($expected, $instance->hasValue($value));
    }

    public function testHasValueAfterRemoveAndPut(): void
    {
        $instance = static::getInstance(['a' => 1]);
        $this->assertTrue($instance->hasValue(1));

        $instance->remove('a');
        $this->assertFalse($instance->hasValue(1));

        $instance->put('a', 1);
        $this->assertTrue($instance->hasValue(1));
    }
}

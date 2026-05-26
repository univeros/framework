<?php
namespace Altair\Tests\Structure\Map;

trait hasKey
{
    public static function hasKeyDataProvider(): array
    {
        // initial, key, has
        return [
            [[],            'a',    false],
            [['a' => 1],    'a',    true],
            [['a' => 1],    'b',    false],
        ];
    }

    /**
     * @dataProvider hasKeyDataProvider
     */
    public function testHasKey(array $initial, mixed $key, bool $has): void
    {
        $instance = static::getInstance($initial);
        $this->assertEquals($has, $instance->hasKey($key));
    }

    public function testHasKeyAfterRemoveAndPut(): void
    {
        $instance = static::getInstance(['a' => 1]);
        $this->assertTrue($instance->hasKey('a'));

        $instance->remove('a');
        $this->assertFalse($instance->hasKey('a'));

        $instance->put('a', 1);
        $this->assertTrue($instance->hasKey('a'));
    }
}

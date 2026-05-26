<?php
namespace Altair\Tests\Structure\Map;

use Altair\Structure\Set;

use PHPUnit\Framework\Attributes\DataProvider;
trait keys
{
    public static function keysDataProvider(): array
    {
        return [
            [[], []],
            [['a' => 1, 'b' => 2], ['a', 'b']],
            [range(0, self::MANY), range(0, self::MANY)],
        ];
    }

    #[DataProvider('keysDataProvider')]
    public function testKeys(array $initial, array $expected): void
    {
        $instance = static::getInstance($initial);
        $keys = $instance->keys();

        $this->assertInstanceOf(Set::class, $keys);
        $this->assertEquals($expected, $keys->toArray());
    }
}

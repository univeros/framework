<?php
namespace Altair\Tests\Structure\Map;

use Altair\Structure\Vector;

use PHPUnit\Framework\Attributes\DataProvider;
trait pairs
{
    public static function pairsDataProvider(): array
    {
        return [
            [[], []],
            [['a' => 1, 'b' => 2], [['a', 1], ['b', 2]]],
        ];
    }

    #[DataProvider('pairsDataProvider')]
    public function testPairs(array $initial, array $expected): void
    {
        $instance = static::getInstance($initial);
        $pairs = $instance->pairs();

        $this->assertInstanceOf(Vector::class, $pairs);

        $to_array = fn($pair): array => [$pair->key, $pair->value];

        $this->assertEquals($expected, array_map($to_array, $pairs->toArray()));
    }

    public function testObjectsAreMutableThroughAccess(): void
    {
        $key = new \stdClass();
        $key->state = true;

        $instance = static::getInstance();
        $instance->put($key, 1);

        $instance->pairs()->first()->key->state = false;

        $this->assertFalse($key->state);
        $this->assertFalse($instance->pairs()->first()->key->state);
    }

    public function testKeysAreNotMutableThroughAccess(): void
    {
        $instance = static::getInstance(['a' => 1, 'b' => 2]);
        $instance->pairs()->first()->key = 'c';

        $this->assertEquals('a', $instance->pairs()->first()->key);
    }
}

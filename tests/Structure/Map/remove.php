<?php
namespace Altair\Tests\Structure\Map;

use Altair\Tests\Structure\HashableObject;

use PHPUnit\Framework\Attributes\DataProvider;
trait remove
{
    public static function removeDataProvider(): array
    {
        $o = new \stdClass();

        // initial pairs, key, return, result
        return [
            // Test basic removal
            [[['a', 1], ['b', 2]], 'a', 1, ['b' => 2]],

            // Test removing an object works
            [[[$o, 'x'], ['a', 1]], $o, 'x', ['a' => 1]],

            // Test that removing a null key works
            [[[null, '#'], ['a', 1]], null, '#', ['a' => 1]],
        ];
    }

    public static function removeHashableDataProvider(): array
    {
        // Two objects with the same hash code and equals.
        $h1 = new HashableObject(1);
        $h2 = new HashableObject(1);

        // put pairs, expected pairs
        return [
            [[[$h1, $h2]], $h1, $h2, []],
        ];
    }

    #[DataProvider('removeHashableDataProvider')]
    public function testRemoveHashable(array $initial, mixed $key, mixed $expected, array $result): void
    {
        $this->testRemove($initial, $key, $expected, $result);
    }

    public function testRemoveAllFromFront(): void
    {
        $instance = static::getInstance();

        for ($i = 0; $i < self::MANY; $i++) {
            $instance->put($i, $i);
        }

        for ($i = 0; $i < self::MANY; $i++) {
            $instance->remove($i);
        }

        $this->assertCount(0, $instance);
        $this->assertToArray([], $instance);
        $this->assertTrue($instance->isEmpty());
    }

    public function testRemoveHalfFromMidway(): void
    {
        $instance = static::getInstance();

        for ($i = 0; $i < self::MANY; $i++) {
            $instance->put($i, $i);
        }

        for ($i = self::MANY / 2; $i < self::MANY; $i++) {
            $instance->remove($i);
        }

        $this->assertCount(self::MANY / 2, $instance);
    }

    public function testRandomRemove(): void
    {
        $instance = static::getInstance();
        $reference = [];

        for ($i = 0; $i < 10; $i++) {
            for ($j = 0; $j < self::MANY; $j++) {
                $k = random_int(0, self::MANY * 2);
                $v = mt_rand();

                $reference[$k] = $v;
                $instance[$k] = $v;
            }

            for ($l = 0; $l < self::MANY; $l++) {
                $k = random_int(0, self::MANY * 2);

                unset($reference[$k], $instance[$k]);
            }
        }

        $this->assertToArray($reference, $instance);
    }

    #[DataProvider('removeDataProvider')]
    public function testRemove(array $initial, mixed $key, mixed $expected, array $result): void
    {
        $instance = static::getInstance();

        foreach ($initial as $pair) {
            $instance->put($pair[0], $pair[1]);
        }

        $this->assertEquals($expected, $instance->remove($key));
        $this->assertToArray($result, $instance);
    }

    public function testRemoveDefault(): void
    {
        $instance = static::getInstance();
        $this->assertEquals('a', $instance->remove('?', 'a'));
    }

    public function testRemoveKeyNotFoundIsEqualNull(): void
    {
        $instance = static::getInstance();
        $this->assertEquals(null, $instance->remove('?'));
    }
}

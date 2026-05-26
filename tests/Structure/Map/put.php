<?php
namespace Altair\Tests\Structure\Map;

use Altair\Tests\Structure\HashableObject;

use PHPUnit\Framework\Attributes\DataProvider;
trait put
{
    public static function putDataProvider(): array
    {
        $o = new \stdClass();

        // put pairs, expected pairs
        return [
            [
                // Test using basic object as key.
                [[$o, 1]],
                [[$o, 1]],
            ],
            [
                // Test using string as key.
                [['a', 1]],
                [['a', 1]],
            ],
            [
                // Test that numeric strings are not treated as int.
                [[0, 0], ['0', 1]],
                [[0, 0], ['0', 1]],
            ],
            [
                // Test that a null key is valid
                [[null, null], [null, null]],
                [[null, null]],
            ],
        ];
    }

    public static function putHashableDataProvider(): array
    {
        // Two objects with the same hash code and equals.
        $h1 = new HashableObject(1);
        $h2 = new HashableObject(1);

        // put pairs, expected pairs
        return [
            // // Test that two equivalent hashable objects are the same.
            [
                [[$h1, 1], [$h2, 2]],
                [[$h2, 2]],
            ],
        ];
    }

    #[DataProvider('putDataProvider')]
    public function testPut(array $pairs, array $expected): void
    {
        $instance = static::getInstance();

        foreach ($pairs as $pair) {
            $instance->put($pair[0], $pair[1]);
        }

        foreach ($expected as $pair) {
            $this->assertEquals($pair[1], $instance->get($pair[0]));
        }

        $this->assertCount(count($expected), $instance);
    }

    public function testPutMany(): void
    {
        $instance = static::getInstance();

        for ($i = 0; $i < self::MANY; $i++) {
            $instance->put(random_int(0, mt_getrandmax()), random_int(0, mt_getrandmax()));
        }

        $this->assertEquals(self::MANY, count($instance));
        $this->assertEquals(self::MANY, count($instance->toArray()));
    }

    #[DataProvider('putHashableDataProvider')]
    public function testPutHashable(array $pairs, array $expected): void
    {
        $this->testPut($pairs, $expected);
    }

    public function testArrayAccessPut(): void
    {
        $instance = static::getInstance(['a' => 1]);
        $instance['a'] = 2;
        $this->assertToArray(['a' => 2], $instance);
    }

    public function testArrayAccessPutByReference(): void
    {
        $instance = static::getInstance(['a' => [1]]);
        $instance['a'][0] = 2;

        $this->assertToArray(['a' => [2]], $instance);
    }

    public function testMapPutCircularReference(): void
    {
        $a = static::getInstance();
        $b = static::getInstance();

        $a->put('B', $b);
        $a->put('A', $a);

        $b->put('B', $b);
        $b->put('A', $a);

        $this->assertToArray(['B' => $b, 'A' => $a], $a);
        $this->assertToArray(['B' => $b, 'A' => $a], $b);
    }
}

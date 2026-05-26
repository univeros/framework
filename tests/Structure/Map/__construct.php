<?php
namespace Altair\Tests\Structure\Map;

use Altair\Structure\Map;

trait __construct
{
    public static function constructDataProvider(): array
    {
        return array_map(fn($a): array => [$a, $a], [
            [],
            ['a' => 1],
            ['a' => 1, 'b' => 2],
            ['a' => 1, 'b' => 2, 'c' => 3],
            static::sample(),
        ]);
    }

    /**
     * @dataProvider constructDataProvider
     */
    public function testConstruct(array $values, array $expected): void
    {
        $this->assertToArray($expected, new Map($values));
    }

    /**
     * @dataProvider constructDataProvider
     */
    public function testConstructUsingNonArrayIterable(array $values, array $expected): void
    {
        $this->assertToArray($expected, new Map(new \ArrayIterator($values)));
    }

    public function testConstructNoParams(): void
    {
        $this->assertToArray([], new Map());
    }
}

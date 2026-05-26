<?php
namespace Altair\Tests\Structure\Deque;

use Altair\Structure\Deque;

trait __construct
{
    public static function constructDataProvider(): array
    {
        return array_map(fn($a): array => [$a, $a], [
            [],
            ['a'],
            ['a', 'b'],
            ['a', 'b', 'c'],
            static::sample(),
            range(1, self::MANY),
        ]);
    }

    /**
     * @dataProvider constructDataProvider
     */
    public function testConstruct(mixed $values, array $expected): void
    {
        $this->assertToArray($expected, new Deque($values));
    }

    /**
     * @dataProvider constructDataProvider
     */
    public function testConstructUsingNonArrayIterable(array $values, array $expected): void
    {
        $this->assertToArray($expected, new Deque(new \ArrayIterator($values)));
    }

    public function testConstructNoParams(): void
    {
        $this->assertToArray([], new Deque());
    }
}

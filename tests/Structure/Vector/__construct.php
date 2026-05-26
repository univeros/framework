<?php
namespace Altair\Tests\Structure\Vector;

use Altair\Structure\Vector;

use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('constructDataProvider')]
    public function testConstruct(mixed $values, array $expected): void
    {
        $this->assertToArray($expected, new Vector($values));
    }

    #[DataProvider('constructDataProvider')]
    public function testConstructUsingNonArrayIterable(array $values, array $expected): void
    {
        $this->assertToArray($expected, new Vector(new \ArrayIterator($values)));
    }

    public function testConstructNoParams(): void
    {
        $this->assertToArray([], new Vector());
    }
}

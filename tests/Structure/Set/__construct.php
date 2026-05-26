<?php
namespace Altair\Tests\Structure\Set;

use Altair\Structure\Set;

use PHPUnit\Framework\Attributes\DataProvider;
trait __construct
{
    public static function constructDataProvider(): array
    {
        [$unique, $duplicated] = static::getUniqueAndDuplicateData();

        return [
            [[],            []],
            [['a'],         ['a']],
            [['a', 'a'],    ['a']],
            [['a', 'b'],    ['a', 'b']],
            [$unique,       $unique],
            [$duplicated,   $unique],
        ];
    }

    #[DataProvider('constructDataProvider')]
    public function testConstruct(array $values, array $expected): void
    {
        $this->assertToArray($expected, new Set($values));
    }

    #[DataProvider('constructDataProvider')]
    public function testConstructUsingIterable(array $values, array $expected): void
    {
        $this->assertToArray($expected, new Set(new \ArrayIterator($values)));
    }

    public function testConstructNoParams(): void
    {
        $this->assertToArray([], new Set());
    }
}

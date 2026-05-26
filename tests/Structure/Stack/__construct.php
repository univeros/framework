<?php
namespace Altair\Tests\Structure\Stack;

use Altair\Structure\Stack;

use PHPUnit\Framework\Attributes\DataProvider;
trait __construct
{
    public static function constructDataProvider(): array
    {
        return [
            [[]],
            [['a']],
            [['a', 'a']],
            [['a', 'b']],
            [static::sample()],
        ];
    }

    #[DataProvider('constructDataProvider')]
    public function testConstruct(array $values): void
    {
        $this->assertToArray(array_reverse($values), new Stack($values));
    }

    #[DataProvider('constructDataProvider')]
    public function testConstructUsingIterable(array $values): void
    {
        $this->assertToArray(array_reverse($values), new Stack(new \ArrayIterator($values)));
    }

    public function testConstructNoParams(): void
    {
        $this->assertToArray([], new Stack());
    }
}

<?php
namespace Altair\Tests\Structure\Stack;

use Altair\Structure\Stack;

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

    /**
     * @dataProvider constructDataProvider
     */
    public function testConstruct(array $values): void
    {
        $this->assertToArray(array_reverse($values), new Stack($values));
    }

    /**
     * @dataProvider constructDataProvider
     */
    public function testConstructUsingIterable(array $values): void
    {
        $this->assertToArray(array_reverse($values), new Stack(new \ArrayIterator($values)));
    }

    public function testConstructNoParams(): void
    {
        $this->assertToArray([], new Stack());
    }
}

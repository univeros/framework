<?php
namespace Altair\Tests\Structure\Queue;

use Altair\Structure\Queue;

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
        $this->assertToArray($values, new Queue($values));
    }

    #[DataProvider('constructDataProvider')]
    public function testConstructUsingIterable(array $values): void
    {
        $this->assertToArray($values, new Queue(new \ArrayIterator($values)));
    }

    public function testConstructNoParams(): void
    {
        $this->assertToArray([], new Queue());
    }
}

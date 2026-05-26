<?php
namespace Altair\Tests\Structure\PriorityQueue;


use PHPUnit\Framework\Attributes\DataProvider;
trait peek
{
    public static function peekDataProvider(): array
    {
        // initial, expected
        return [
            [['a' => 1, 'b' => 2], 'b'],
            [['a' => 2, 'b' => 1], 'a'],
            [['a' => 1, 'b' => 1], 'a'],
        ];
    }

    #[DataProvider('peekDataProvider')]
    public function testPeek(array $initial, mixed $expected): void
    {
        $instance = static::getInstance($initial);
        $this->assertEquals($expected, $instance->peek());
        $this->assertCount(count($initial), $instance);
    }

    public function testPeekNotAllowedWhenEmpty(): void
    {
        $instance = static::getInstance();
        $this->expectEmptyNotAllowedException();
        $instance->peek();
    }
}

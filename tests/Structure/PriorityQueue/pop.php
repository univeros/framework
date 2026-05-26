<?php
namespace Altair\Tests\Structure\PriorityQueue;


use PHPUnit\Framework\Attributes\DataProvider;
trait pop
{
    public static function popDataProvider(): array
    {
        // initial, expected, result
        return [
            [['a' => 1, 'b' => 2], 'b', ['a']],
            [['a' => 2, 'b' => 1], 'a', ['b']],
            [['a' => 1, 'b' => 1], 'a', ['b']],
        ];
    }

    #[DataProvider('popDataProvider')]
    public function testPop(array $initial, mixed $expected, array $result): void
    {
        $instance = static::getInstance($initial);

        $this->assertEquals($expected, $instance->pop());
        $this->assertToArray($result, $instance);
    }

    public function testPopAll(): void
    {
        $instance = static::getInstance(range(1, self::MANY));

        while (!$instance->isEmpty()) {
            $instance->pop();
        }

        $this->assertEquals(count($instance), 0);
    }

    public function testPopNowAllowedWhenEmpty(): void
    {
        $instance = static::getInstance();
        $this->expectEmptyNotAllowedException();
        $instance->pop();
    }
}

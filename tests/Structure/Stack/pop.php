<?php
namespace Altair\Tests\Structure\Stack;


use PHPUnit\Framework\Attributes\DataProvider;
trait pop
{
    public static function popDataProvider(): array
    {
        // initial, returned, expected result
        return [
            [['a'],       'a',  []],
            [['a', 'b'],  'b',  ['a']],
        ];
    }

    #[DataProvider('popDataProvider')]
    public function testPop(mixed $initial, mixed $returned, array $expected): void
    {
        $instance = static::getInstance($initial);

        $result = $instance->pop();

        $this->assertToArray($expected, $instance);
        $this->assertEquals($returned, $result);
        $this->assertEquals(count($initial) - 1, count($instance));
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

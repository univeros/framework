<?php
namespace Altair\Tests\Structure\Queue;

trait pop
{
    public static function popDataProvider(): array
    {
        // initial, returned, expected result
        return [
            [['a'],         'a',    []],
            [['a', 'b'],    'a',    ['b']],
        ];
    }

    /**
     * @dataProvider popDataProvider
     */
    public function testPop(mixed $initial, mixed $returned, array $expected): void
    {
        $instance = static::getInstance($initial);

        $value = $instance->pop();

        $this->assertToArray($expected, $instance);
        $this->assertEquals($returned, $value);
        $this->assertEquals(count($initial) - 1, count($instance));
    }

    public function testPopNotAllowedWhenEmpty(): void
    {
        $instance = static::getInstance();
        $this->expectEmptyNotAllowedException();
        $instance->pop();
    }

    public function testPopAll(): void
    {
        $instance = static::getInstance(range(1, self::MANY));

        while (!$instance->isEmpty()) {
            $instance->pop();
        }

        $this->assertEquals(count($instance), 0);
    }
}

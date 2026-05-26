<?php
namespace Altair\Tests\Structure\Sequence;

trait pop
{
    public static function popDataProvider(): array
    {
        // initial, expected, returned
        return [
            [['a'],         [],     'a'],
            [['a', 'b'],    ['a'],  'b'],
        ];
    }

    /**
     * @dataProvider popDataProvider
     */
    public function testPop(mixed $initial, array $expected, mixed $returned): void
    {
        $instance = static::getInstance($initial);

        $result = $instance->pop();

        $this->assertToArray($expected, $instance);
        $this->assertEquals($returned, $result);
        $this->assertEquals(count($initial) - 1, count($instance));

        //
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

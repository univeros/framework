<?php
namespace Altair\Tests\Structure\Map;

trait last
{
    public static function lastDataProvider(): array
    {
        // initial, returned
        return [
            [['a'],             [0, 'a']],
            [['a', 'b'],        [1, 'b']],
            [['a', 'b', 'c'],   [2, 'c']],
        ];
    }

    /**
     * @dataProvider lastDataProvider
     */
    public function testLast(mixed $initial, mixed $expected): void
    {
        $instance = static::getInstance($initial);
        $last = $instance->last();

        $this->assertEquals($expected, [$last->key, $last->value]);
    }

    public function testLastNotAllowedWhenEmpty(): void
    {
        $instance = static::getInstance();
        $this->expectEmptyNotAllowedException();
        $instance->last();
    }
}

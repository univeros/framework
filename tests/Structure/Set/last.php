<?php
namespace Altair\Tests\Structure\Set;

trait last
{
    public static function lastDataProvider(): array
    {
        // initial, returned
        return [
            [['a'],             'a'],
            [['a', 'b'],        'b'],
            [['a', 'b', 'c'],   'c'],
        ];
    }

    /**
     * @dataProvider lastDataProvider
     */
    public function testLast(mixed $initial, mixed $expected): void
    {
        $instance = static::getInstance($initial);
        $this->assertEquals($expected, $instance->last());
    }

    public function testLastNotAllowedWhenEmpty(): void
    {
        $instance = static::getInstance();
        $this->expectEmptyNotAllowedException();
        $instance->last();
    }
}

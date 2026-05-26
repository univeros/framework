<?php
namespace Altair\Tests\Structure\Map;

trait first
{
    public static function firstDataProvider(): array
    {
        // initial, returned
        return [
            [['a'],             [0, 'a']],
            [['a', 'b'],        [0, 'a']],
            [['a', 'b', 'c'],   [0, 'a']],
        ];
    }

    /**
     * @dataProvider firstDataProvider
     */
    public function testFirst(array $initial, mixed $expected): void
    {
        $instance = static::getInstance($initial);
        $first = $instance->first();

        $this->assertEquals($expected, [$first->key, $first->value]);
    }

    public function testFirstNowAllowedWhenEmpty(): void
    {
        $instance = static::getInstance();
        $this->expectEmptyNotAllowedException();
        $instance->first();
    }
}

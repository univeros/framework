<?php
namespace Altair\Tests\Structure\Sequence;


use PHPUnit\Framework\Attributes\DataProvider;
trait first
{
    public static function firstDataProvider(): array
    {
        // initial, returned
        return [
            [['a'],             'a'],
            [['a', 'b'],        'a'],
            [['a', 'b', 'c'],   'a'],
        ];
    }

    #[DataProvider('firstDataProvider')]
    public function testFirst(array $initial, mixed $expected): void
    {
        $instance = static::getInstance($initial);
        $this->assertEquals($expected, $instance->first());
    }

    public function testFirstNowAllowedWhenEmpty(): void
    {
        $instance = static::getInstance();
        $this->expectEmptyNotAllowedException();
        $instance->first();
    }
}

<?php
namespace Altair\Tests\Structure\Queue;

trait peek
{
    public static function peekDataProvider(): array
    {
        // initial, returned, expected result
        return [
            [['a'],         'a'],
            [['a', 'b'],    'a'],
        ];
    }

    /**
     * @dataProvider peekDataProvider
     */
    public function testPeek(mixed $initial, mixed $returned): void
    {
        $instance = static::getInstance($initial);

        $value = $instance->peek();

        $this->assertToArray($initial, $instance);
        $this->assertEquals($returned, $value);
    }

    public function testPeekNotAllowedWhenEmpty(): void
    {
        $instance = static::getInstance();
        $this->expectEmptyNotAllowedException();
        $instance->peek();
    }
}

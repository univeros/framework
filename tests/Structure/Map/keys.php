<?php
namespace Altair\Tests\Structure\Map;

use Altair\Structure\Set;

trait keys
{
    public function keysDataProvider()
    {
        return [
            [[], []],
            [['a' => 1, 'b' => 2], ['a', 'b']],
            [range(0, self::MANY), range(0, self::MANY)],
        ];
    }

    /**
     * @dataProvider keysDataProvider
     */
    public function testKeys(array $initial, array $expected)
    {
        $instance = $this->getInstance($initial);
        $keys = $instance->keys();

        $this->assertInstanceOf(Set::class, $keys);
        $this->assertEquals($expected, $keys->toArray());
    }
}

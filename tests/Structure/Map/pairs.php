<?php
namespace Altair\Tests\Structure\Map;

use Altair\Structure\Vector;

trait pairs
{
    public function pairsDataProvider()
    {
        return [
            [[], []],
            [['a' => 1, 'b' => 2], [['a', 1], ['b', 2]]],
        ];
    }

    /**
     * @dataProvider pairsDataProvider
     */
    public function testPairs(array $initial, array $expected)
    {
        $instance = $this->getInstance($initial);
        $pairs = $instance->pairs();

        $this->assertInstanceOf(Vector::class, $pairs);

        $to_array = function ($pair) {
            return [$pair->key, $pair->value];
        };

        $this->assertEquals($expected, array_map($to_array, $pairs->toArray()));
    }

    public function testObjectsAreMutableThroughAccess()
    {
        $key = new \stdClass();
        $key->state = true;

        $instance = $this->getInstance();
        $instance->put($key, 1);

        $instance->pairs()->first()->key->state = false;

        $this->assertFalse($key->state);
        $this->assertFalse($instance->pairs()->first()->key->state);
    }

    public function testKeysAreNotMutableThroughAccess()
    {
        $instance = $this->getInstance(['a' => 1, 'b' => 2]);
        $instance->pairs()->first()->key = 'c';

        $this->assertEquals('a', $instance->pairs()->first()->key);
    }
}

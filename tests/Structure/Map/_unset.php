<?php
namespace Altair\Tests\Structure\Map;

trait _unset
{
    public function testArrayAccessUnset()
    {
        $instance = static::getInstance(['a' => 1]);
        unset($instance['a']);
        $this->assertToArray([], $instance);
    }

    public function testArrayAccessUnsetByReference()
    {
        $instance = static::getInstance(['a' => [1]]);
        unset($instance['a'][0]);

        $this->assertToArray(['a' => []], $instance);
    }
}

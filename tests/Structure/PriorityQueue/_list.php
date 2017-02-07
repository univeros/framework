<?php
namespace Altair\Tests\Structure\PriorityQueue;

trait _list
{
    public function testList()
    {
        // PriorityQueue does not return array with index access
        $instance = $this->getInstance(['a' => 1, 'b' => 2]);
        $arr = $instance->toArray();
        $this->assertFalse(isset($arr['a']));
    }
}

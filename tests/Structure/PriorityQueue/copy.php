<?php
namespace Altair\Tests\Structure\PriorityQueue;

trait copy
{
    /**
     * @dataProvider basicDataProvider
     */
    public function testCopy(array $values, array $expected)
    {
        $instance = $this->getInstance($values);
        $copy = $instance->copy();

        $this->assertEquals($instance->toArray(), $copy->toArray());
        $this->assertEquals(count($instance), count($copy));
    }

    public function testCopyDoesNotAffectSubject()
    {
        $instance = $this->getInstance();
        $instance->push('a', 1);
        $instance->push('b', 2);
        $instance->push('c', 3);

        $copy = $instance->copy();

        $instance->pop();

        $this->assertEquals(2, count($instance));
        $this->assertEquals(3, count($copy));
    }
}

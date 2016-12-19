<?php
namespace Altair\Tests\Structure\Set;

trait _clone
{
    /**
     * @dataProvider basicDataProvider
     * @param mixed $values
     */
    public function testClone($values, array $expected)
    {
        $instance = $this->getInstance($values);

        $clone = clone $instance;

        $this->assertEquals(get_class($instance), get_class($clone));
        $this->assertEquals($instance->toArray(), $clone->toArray());
        $this->assertFalse($clone === $instance);
    }
}

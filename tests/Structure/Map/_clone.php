<?php
namespace Altair\Tests\Structure\Map;

trait _clone
{
    public function cloneDataProvider()
    {
        return $this->basicDataProvider();
    }

    /**
     * @dataProvider cloneDataProvider
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

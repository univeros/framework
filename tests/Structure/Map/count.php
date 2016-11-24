<?php
namespace Altair\tests\Structure\Map;

trait count
{
    public function testCount()
    {
        $instance = $this->getInstance($this->sample());
        $this->assertCount(count($this->sample()), $instance);
    }

    public function testCountEmpty()
    {
        $instance = $this->getInstance();
        $this->assertCount(0, $instance);
    }
}

<?php
namespace Altair\Tests\Structure\Sequence;

trait count
{
    public function testCount()
    {
        $instance = static::getInstance(static::sample());
        $this->assertCount(count(static::sample()), $instance);
    }

    public function testCountEmpty()
    {
        $instance = static::getInstance();
        $this->assertCount(0, $instance);
    }
}

<?php
namespace Altair\Tests\Structure\Map;

trait count
{
    public function testCount(): void
    {
        $instance = static::getInstance(static::sample());
        $this->assertCount(count(static::sample()), $instance);
    }

    public function testCountEmpty(): void
    {
        $instance = static::getInstance();
        $this->assertCount(0, $instance);
    }
}

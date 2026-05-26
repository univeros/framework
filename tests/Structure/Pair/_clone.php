<?php
namespace Altair\Tests\Structure\Pair;

trait _clone
{
    public function testClone(): void
    {
        $instance = $this->getPair('a', 1);

        $clone = clone $instance;

        $this->assertEquals($instance::class, $clone::class);
        $this->assertEquals($instance->toArray(), $clone->toArray());
        $this->assertFalse($clone === $instance);
    }
}

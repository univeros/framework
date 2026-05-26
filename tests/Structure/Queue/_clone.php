<?php
namespace Altair\Tests\Structure\Queue;

trait _clone
{
    /**
     * @dataProvider basicDataProvider
     */
    public function testClone(mixed $values, array $expected): void
    {
        $instance = static::getInstance($values);

        $clone = clone $instance;

        $this->assertEquals($instance::class, $clone::class);
        $this->assertEquals($instance->toArray(), $clone->toArray());
        $this->assertFalse($clone === $instance);
    }
}

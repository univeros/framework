<?php
namespace Altair\tests\Structure\Vector;

use Altair\Structure\Contracts\VectorInterface;

trait capacity
{
    public function testCapacity()
    {
        $min = VectorInterface::MIN_VECTOR_CAPACITY;

        $instance = $this->getInstance();
        $this->assertEquals($min, $instance->capacity());

        for ($i = 0; $i < $min; $i++) {
            $instance->push($i);
        }

        // Should not resize when full after push
        $this->assertEquals($min, $instance->capacity());

        // Should resize if full before push
        $instance->push('x');
        $this->assertEquals(intval($min * 1.5), $instance->capacity());
    }

    public function testAutoTruncate()
    {
        $min = VectorInterface::MIN_VECTOR_CAPACITY;

        $instance = $this->getInstance(range(1, self::MANY));
        $expected = $instance->capacity() / 2;

        for ($i = 0; $i <= 3 * self::MANY / 4; $i++) {
            $instance->pop();
        }

        $this->assertEquals($expected, $instance->capacity());
    }

    public function testClearResetsCapacity()
    {
        $min = VectorInterface::MIN_VECTOR_CAPACITY;

        $instance = $this->getInstance(range(1, self::MANY));
        $instance = $instance->clear();
        $this->assertEquals($min, $instance->capacity());
    }
}

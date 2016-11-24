<?php
namespace Altair\tests\Structure\Vector;

use Altair\Structure\Contracts\VectorInterface;

trait allocate
{
    public function allocateDataProvider()
    {
        $m = VectorInterface::MIN_VECTOR_CAPACITY;

        // initial, allocation, expected capacity
        return [

            // Test minimum capacity
            [0,  0, $m],
            [$m,  0, $m],
            [$m, $m, $m],

            // Test boundaries
            [$m, $m * 2 + 1, $m * 2 + 1],
            [$m, $m * 2, $m * 2],
            [$m, $m * 2 - 1, $m * 2 - 1],
        ];
    }

    /**
     * @dataProvider allocateDataProvider
     */
    public function testAllocate(int $initial, int $allocate, int $expected)
    {
        $instance = $this->getInstance();

        $instance->allocate($initial);
        $instance->allocate($allocate);
        $this->assertEquals($expected, $instance->capacity());
    }
}

<?php
namespace Altair\tests\Structure\Deque;

trait allocate
{
    public function allocateDataProvider()
    {
        $m = \Altair\Structure\Contracts\CapacityInterface::MIN_CAPACITY;

        // initial, allocation, expected capacity
        return [

            // Test minimum capacity
            [0,  0,      $m],
            [$m, 0,      $m],
            [$m, $m - 1, $m],

            // Test boundaries
            [$m * 2, $m * 2 + 1, $m * 4],
            [$m * 2, $m * 2, $m * 2],
            [$m * 2, $m * 2 - 1, $m * 2],
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

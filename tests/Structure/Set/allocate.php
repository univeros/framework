<?php
namespace Altair\Tests\Structure\Set;

use Altair\Structure\Contracts\CapacityInterface;

use PHPUnit\Framework\Attributes\DataProvider;
trait allocate
{
    public static function allocateDataProvider(): array
    {
        $m = CapacityInterface::MIN_CAPACITY;

        // initial, allocation, expected capacity
        return [
            // Test minimum capacity
            [0,       0, $m],
            [$m,      0, $m],
            [$m, $m - 1, $m],

            // Test boundaries
            [$m * 2, $m * 2 + 1, $m * 4],
            [$m * 2, $m * 2, $m * 2],
            [$m * 2, $m * 2 - 1, $m * 2],
        ];
    }

    #[DataProvider('allocateDataProvider')]
    public function testAllocate(int $initial, int $allocate, int $expected): void
    {
        $instance = static::getInstance();

        $instance->allocate($initial);
        $instance->allocate($allocate);
        $this->assertEquals($expected, $instance->capacity());
    }
}

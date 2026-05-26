<?php
namespace Altair\Tests\Structure\Set;


use PHPUnit\Framework\Attributes\DataProvider;
trait sum
{
    public static function sumDataProvider(): array
    {
        return [
            // Empty
            [[]],

            // Basic integer sum
            [[1, 2, 3, 4, 5]],

            // Basic float sum
            [[1.5, 2.5, 5.1]],

            // Mixed sum
            [[1.5, 3, 5]],

            // Numeric strings
            [['2', '5', '10.5']],

            // Mixed
            [['2', '5', 10.5, 9]],

            // Mixed with non-numbers
            [['2', '5', 10.5, 9, 'a', new \stdClass()]],
        ];
    }

    #[DataProvider('sumDataProvider')]
    public function testSum(mixed $values): void
    {
        $instance = static::getInstance($values);
        $this->assertEquals(array_sum($values), $instance->sum());
    }
}

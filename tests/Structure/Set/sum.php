<?php
namespace Altair\Tests\Structure\Set;

trait sum
{
    public function sumDataProvider()
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

    /**
     * @dataProvider sumDataProvider
     * @param mixed $values
     */
    public function testSum($values)
    {
        $instance = $this->getInstance($values);
        $this->assertEquals(array_sum($values), $instance->sum());
    }
}

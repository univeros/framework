<?php
namespace Altair\Tests\Structure\PriorityQueue;


use PHPUnit\Framework\Attributes\DataProvider;
trait toArray
{
    #[DataProvider('basicDataProvider')]
    public function testToArray(array $values, array $expected): void
    {
        $instance = static::getInstance($values);

        // Also check that toArray is not destructive
        $this->assertToArray($expected, $instance);
        $this->assertToArray($expected, $instance);
    }
}

<?php
namespace Altair\Tests\Structure\Set;


use PHPUnit\Framework\Attributes\DataProvider;
trait toArray
{
    #[DataProvider('basicDataProvider')]
    public function testToArray(array $values, array $expected): void
    {
        $instance = static::getInstance($values);
        $this->assertToArray($expected, $instance);
    }
}

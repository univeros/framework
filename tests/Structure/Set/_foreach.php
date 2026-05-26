<?php
namespace Altair\Tests\Structure\Set;


use PHPUnit\Framework\Attributes\DataProvider;
trait _foreach
{
    #[DataProvider('basicDataProvider')]
    public function testForEach(array $values, array $expected): void
    {
        $instance = static::getInstance($values);
        $this->assertForEach($expected, $instance);
    }
}

<?php
namespace Altair\Tests\Structure\Queue;


use PHPUnit\Framework\Attributes\DataProvider;
trait count
{
    #[DataProvider('basicDataProvider')]
    public function testCount(array $values, array $expected): void
    {
        $instance = static::getInstance($values);
        $this->assertCount(count($expected), $instance);
    }

    public function testCountEmpty(): void
    {
        $instance = static::getInstance();
        $this->assertCount(0, $instance);
    }
}

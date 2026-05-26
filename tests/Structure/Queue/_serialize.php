<?php
namespace Altair\Tests\Structure\Queue;


use PHPUnit\Framework\Attributes\DataProvider;
trait _serialize
{
    #[DataProvider('basicDataProvider')]
    public function testSerialize(array $values, array $expected): void
    {
        $instance = static::getInstance($values);
        $this->assertSerialized($expected, $instance, false);
    }
}

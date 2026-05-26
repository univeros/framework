<?php
namespace Altair\Tests\Structure\Sequence;


use PHPUnit\Framework\Attributes\DataProvider;
trait _clone
{
    public static function cloneDataProvider()
    {
        return static::basicDataProvider();
    }

    #[DataProvider('cloneDataProvider')]
    public function testClone(mixed $values, array $expected): void
    {
        $instance = static::getInstance($values);

        $clone = clone $instance;

        $this->assertEquals($instance::class, $clone::class);
        $this->assertEquals($instance->toArray(), $clone->toArray());
        $this->assertFalse($clone === $instance);
    }
}

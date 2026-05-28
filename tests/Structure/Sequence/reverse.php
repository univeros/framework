<?php
namespace Altair\Tests\Structure\Sequence;


use PHPUnit\Framework\Attributes\DataProvider;
trait reverse
{
    public static function reversedDataProvider(): array
    {
        return array_map(
            fn(array $a): array => [$a[0], array_reverse($a[1])],
            static::basicDataProvider()
        );
    }

    #[DataProvider('reversedDataProvider')]
    public function testReversed(array $values, array $expected): void
    {
        $instance = static::getInstance($values);
        $this->assertToArray($values, $instance);
        $instance = $instance->reverse();
        $this->assertToArray($expected, $instance);
    }
}

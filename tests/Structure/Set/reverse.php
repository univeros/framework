<?php
namespace Altair\Tests\Structure\Set;

trait reverse
{
    public static function reversedDataProvider(): array
    {
        return array_map(
            fn($a): array => [$a[0], array_reverse($a[1])],
            static::basicDataProvider()
        );
    }

    /**
     * @dataProvider reversedDataProvider
     */
    public function testReversed(array $values, array $expected): void
    {
        $instance = static::getInstance($values);
        $reversed = $instance->reverse();

        $this->assertToArray($expected, $reversed);
        $this->assertToArray($values, $instance);
    }
}

<?php
namespace Altair\Tests\Structure\PriorityQueue;


use PHPUnit\Framework\Attributes\DataProvider;
trait _serialize
{
    public static function serializeDataProvider(): array
    {
        return [
            [
                ['a' => 1, 'b' => 2], ['b' => 2, 'a' => 1],
            ],
        ];
    }

    #[DataProvider('serializeDataProvider')]
    public function testSerialize(array $values, array $expected): void
    {
        $instance = static::getInstance($values);
        $this->assertSerialized($expected, $instance, true);
    }
}

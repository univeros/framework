<?php
namespace Altair\Tests\Structure\Map;

use Altair\Structure\Pair;

use PHPUnit\Framework\Attributes\DataProvider;
trait _var_dump
{
    public static function varDumpDataProvider(): array
    {
        // values, expected array repr
        return [
            [
                [],
                [],
            ],
            [
                ['a'],
                [new Pair(0, 'a')],
            ],
            [
                ['a', 'b'],
                [new Pair(0, 'a'), new Pair(1, 'b')],
            ],
        ];
    }

    #[DataProvider('varDumpDataProvider')]
    public function testVarDump(array $values, array $expected): void
    {
        $instance = static::getInstance($values);
        $this->assertInstanceDump($expected, $instance);
    }
}

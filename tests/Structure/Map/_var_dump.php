<?php
namespace Altair\Tests\Structure\Map;

use Altair\Structure\Pair;

trait _var_dump
{
    public function varDumpDataProvider()
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

    /**
     * @dataProvider varDumpDataProvider
     */
    public function testVarDump(array $values, array $expected)
    {
        $instance = $this->getInstance($values);
        $this->assertInstanceDump($expected, $instance);
    }
}

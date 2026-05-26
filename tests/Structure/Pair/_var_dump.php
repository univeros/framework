<?php
namespace Altair\Tests\Structure\Pair;

trait _var_dump
{
    public function testVarDump(): void
    {
        $instance = $this->getPair('a', 1);
        $expected = [
            'key' => 'a',
            'value' => 1,
        ];

        $this->assertInstanceDump($expected, $instance);
    }
}

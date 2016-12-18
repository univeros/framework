<?php
namespace Altair\Tests\Structure\Stack;

trait _var_dump
{
    /**
     * @dataProvider basicDataProvider
     */
    public function testVarDump(array $values, array $expected)
    {
        $instance = $this->getInstance($values);
        $this->assertInstanceDump($expected, $instance);
    }
}

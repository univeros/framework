<?php
namespace Altair\Tests\Structure\Sequence;

trait _var_dump
{
    /**
     * @dataProvider basicDataProvider
     */
    public function testVarDump(array $values, array $expected): void
    {
        $instance = static::getInstance($values);
        $this->assertInstanceDump($expected, $instance);
    }
}

<?php
namespace Altair\Tests\Structure\Stack;


use PHPUnit\Framework\Attributes\DataProvider;
trait _var_dump
{
    #[DataProvider('basicDataProvider')]
    public function testVarDump(array $values, array $expected): void
    {
        $instance = static::getInstance($values);
        $this->assertInstanceDump($expected, $instance);
    }
}

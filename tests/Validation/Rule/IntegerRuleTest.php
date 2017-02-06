<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Rule\IntegerRule;

class IntegerRuleTest extends AbstractRuleTest
{
    public function trueProvider()
    {
        return [
            ["+1234567890"],
            [1234567890],
            [-123456789.0],
            [-1234567890],
            ['-123'],
        ];
    }

    public function falseProvider()
    {
        return [
            [' '],
            [''],
            ["-abc.123"],
            ["123.abc"],
            ["123,456"],
            ['0000123.456000'],
        ];
    }

    protected function buildRule()
    {
        return new IntegerRule();
    }
}

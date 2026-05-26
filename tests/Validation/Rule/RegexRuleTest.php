<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Rule\RegexRule;

class RegexRuleTest extends AbstractRuleTest
{
    #[\Override]
    public static function trueProvider(): array
    {
        return [
            ['+1234567890'],
            [1234567890],
            [-123456789.0],
            [-1234567890],
            ['-123'],
        ];
    }

    #[\Override]
    public static function falseProvider(): array
    {
        return [
            [[]],
            [' '],
            [''],
            ['-abc.123'],
            ['123.abc'],
            ['123),456'],
            ['0000123.456000'],
        ];
    }

    #[\Override]
    protected function buildRule(): RegexRule
    {
        return new RegexRule('/^[\+\-]?\d+$/');
    }
}

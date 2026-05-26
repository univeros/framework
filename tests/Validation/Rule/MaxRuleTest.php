<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Rule\MaxRule;

class MaxRuleTest extends AbstractRuleTest
{
    #[\Override]
    public static function trueProvider(): array
    {
        return [
            [1],
            [2],
            [3],
        ];
    }

    #[\Override]
    public static function falseProvider(): array
    {
        return [
            [[]],
            [4],
            [5],
            [6],
        ];
    }

    #[\Override]
    protected function buildRule(): MaxRule
    {
        return new MaxRule(3);
    }
}

<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Rule\MinRule;

class MinRuleTest extends AbstractRuleTest
{
    #[\Override]
    public static function trueProvider(): array
    {
        return [
            [4],
            [5],
            [6],
        ];
    }

    #[\Override]
    public static function falseProvider(): array
    {
        return [
            [[]],
            [1],
            [2],
            [3],
        ];
    }

    #[\Override]
    protected function buildRule(): MinRule
    {
        return new MinRule(4);
    }
}

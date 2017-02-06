<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Rule\MaxRule;

class MaxRuleTest extends AbstractRuleTest
{
    public function trueProvider()
    {
        return [
            [1],
            [2],
            [3],
        ];
    }

    public function falseProvider()
    {
        return [
            [[]],
            [4],
            [5],
            [6],
        ];
    }

    protected function buildRule()
    {
        return new MaxRule(3);
    }
}

<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Rule\BetweenRule;

class BetweenRuleTest extends AbstractRuleTest
{
    public function trueProvider()
    {
        return [
            [2],
            [3],
            [4],
            [5],
            [6]
        ];
    }

    public function falseProvider()
    {
        return [
            [-1],
            [1],
            [7]
        ];
    }

    protected function buildRule()
    {
        return new BetweenRule(2, 6);
    }
}

<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Rule\MinRule;

class MinRuleTest extends AbstractRuleTest
{
    public function trueProvider()
    {
        return [
            [4],
            [5],
            [6],
        ];
    }

    public function falseProvider()
    {
        return [
            [[]],
            [1],
            [2],
            [3],
        ];
    }

    protected function buildRule()
    {
        return new MinRule(4);
    }
}

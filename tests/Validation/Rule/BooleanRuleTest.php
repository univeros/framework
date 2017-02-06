<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Rule\BooleanRule;

class BooleanRuleTest extends AbstractRuleTest
{
    public function trueProvider()
    {
        return [
            [true],
            ['on'],
            ['On'],
            ['ON'],
            ['yes'],
            ['Yes'],
            ['YeS'],
            ['true'],
            ['True'],
            ['TrUe'],
            [1],
            ['1'],
            [false],
            ['off'],
            ['Off'],
            ['OfF'],
            ['no'],
            ['No'],
            ['NO'],
            ['false'],
            ['False'],
            ['FaLsE'],
            [0],
            ['0'],
        ];
    }

    public function falseProvider()
    {
        return [
            ['nothing'],
            [123],
            [[1]],
        ];
    }

    protected function buildRule()
    {
        return new BooleanRule();
    }
}

<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Rule\CallbackRule;

class CallbackRuleTest extends AbstractRuleTest
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
        $callback = function ($value) {
            // lets mimic boolean rule
            // it can be anything though
            return is_bool(filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE));
        };
        return new CallbackRule($callback);
    }
}

<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Rule\AlphaRule;

class AlphaRuleTest extends AbstractRuleTest
{
    public static function trueProvider()
    {
        return [
            ['alphaOnly'],
            ['AlphaOnly'],
            ['AlphaOnlyБуква'],
            ['самоБуква']
        ];
    }

    public static function falseProvider()
    {
        return [
            ['  '],
            [''],
            [0],
            [1],
            ['1'],
            ['5'],
            ['Nine 10 eleven'],
            ['none: alpha-signs'],
            ['4alph4'],
            ['Буква88'],
            [null]
        ];
    }

    protected function buildRule()
    {
        return new AlphaRule();
    }
}

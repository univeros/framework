<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Rule\AlphaNumRule;

class AlphaNumRuleTest extends AbstractRuleTest
{
    public function trueProvider()
    {
        return [
            [0],
            [1],
            [2],
            [5],
            ['0'],
            ['1'],
            ['2'],
            ['5'],
            ['alphaonly'],
            ['AlphaOnLy'],
            ['someThing8else'],
            ['soЗѝЦЯng8else'],
        ];
    }

    public function falseProvider()
    {
        return [
            ['  '],
            [''],
            ['N=ne 10 eleven'],
            ['none: alpha-signs'],
            ['ЕФГ35%-№'],
            ['Буква8.8'],
            [null]
        ];
    }

    protected function buildRule()
    {
        return new AlphaNumRule();
    }
}

<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Rule\AlphaNumRule;

class AlphaNumRuleTest extends AbstractRuleTest
{
    #[\Override]
    public static function trueProvider(): array
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

    #[\Override]
    public static function falseProvider(): array
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

    #[\Override]
    protected function buildRule(): AlphaNumRule
    {
        return new AlphaNumRule();
    }
}

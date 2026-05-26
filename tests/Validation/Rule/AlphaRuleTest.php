<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Rule\AlphaRule;

class AlphaRuleTest extends AbstractRuleTest
{
    #[\Override]
    public static function trueProvider(): array
    {
        return [
            ['alphaOnly'],
            ['AlphaOnly'],
            ['AlphaOnlyБуква'],
            ['самоБуква']
        ];
    }

    #[\Override]
    public static function falseProvider(): array
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

    #[\Override]
    protected function buildRule(): AlphaRule
    {
        return new AlphaRule();
    }
}

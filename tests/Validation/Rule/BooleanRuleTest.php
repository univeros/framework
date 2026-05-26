<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Rule\BooleanRule;

class BooleanRuleTest extends AbstractRuleTest
{
    #[\Override]
    public static function trueProvider(): array
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

    #[\Override]
    public static function falseProvider(): array
    {
        return [
            ['nothing'],
            [123],
            [[1]],
        ];
    }

    #[\Override]
    protected function buildRule(): BooleanRule
    {
        return new BooleanRule();
    }
}

<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Rule\SwiftBicRule;

class SwiftBicRuleTest extends AbstractRuleTest
{
    #[\Override]
    public static function trueProvider(): array
    {
        return [
            ['RBOSGGSX'],
            ['RZTIAT22263'],
            ['BCEELULL'],
            ['MARKDEFF'],
            ['GENODEF1JEV'],
            ['UBSWCHZH80A'],
            ['CEDELULLXXX'],
        ];
    }

    #[\Override]
    public static function falseProvider(): array
    {
        return [
            ['CE1EL2LLFFF'],
            ['E31DCLLFFF'],
        ];
    }

    #[\Override]
    protected function buildRule(): SwiftBicRule
    {
        return new SwiftBicRule();
    }
}

<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Rule\SwiftBicRule;

class SwiftBicRuleTest extends AbstractRuleTest
{
    public static function trueProvider()
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

    public static function falseProvider()
    {
        return [
            ['CE1EL2LLFFF'],
            ['E31DCLLFFF'],
        ];
    }

    protected function buildRule()
    {
        return new SwiftBicRule();
    }
}

<?php

declare(strict_types=1);

namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Rule\CallbackRule;

class CallbackRuleTest extends AbstractRuleTest
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
    protected function buildRule(): CallbackRule
    {
        $callback = fn($value): bool =>
            // lets mimic boolean rule
            // it can be anything though
            is_bool(filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE));
        return new CallbackRule($callback);
    }
}

<?php

declare(strict_types=1);

namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Rule\BetweenRule;

class BetweenRuleTest extends AbstractRuleTest
{
    #[\Override]
    public static function trueProvider(): array
    {
        return [
            [2],
            [3],
            [4],
            [5],
            [6]
        ];
    }

    #[\Override]
    public static function falseProvider(): array
    {
        return [
            [-1],
            [1],
            [7]
        ];
    }

    #[\Override]
    protected function buildRule(): BetweenRule
    {
        return new BetweenRule(2, 6);
    }
}

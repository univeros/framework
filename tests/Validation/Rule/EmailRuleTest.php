<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Rule\EmailRule;

class EmailRuleTest extends AbstractRuleTest
{
    #[\Override]
    public static function trueProvider(): array
    {
        return [
            ['test@test.com'],
            ['mail+mail@gmail.com'],
            ['mail.email@e.test.com'],
            ['a@a.a'],
        ];
    }

    #[\Override]
    public static function falseProvider(): array
    {
        return [
            [''],
            ['test@test'],
            ['test'],
            ['test@тест.рф'],
            ['@test.com'],
            ['mail@test@test.com'],
            ['test.test@'],
            ['test.@test.com'],
            ['test@.test.com'],
            ['test@test..com'],
            ['test@test.com.'],
            ['.test@test.com'],
        ];
    }

    #[\Override]
    protected function buildRule(): EmailRule
    {
        return new EmailRule();
    }
}

<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Rule\EmailRule;

class EmailRuleTest extends AbstractRuleTest
{
    public function trueProvider()
    {
        return [
            ['test@test.com'],
            ['mail+mail@gmail.com'],
            ['mail.email@e.test.com'],
            ['a@a.a'],
        ];
    }

    public function falseProvider()
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

    protected function buildRule()
    {
        return new EmailRule();
    }
}

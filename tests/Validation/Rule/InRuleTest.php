<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Contracts\PayloadInterface;
use Altair\Validation\Rule\InRule;

class InRuleTest extends AbstractRuleTest
{
    public function trueProvider()
    {
        return [
            ['val0'],
            ['val1'],
            ['val3'],
        ];
    }

    public function falseProvider()
    {
        return [
            [5],
            [4],
            ['x'],
            ['z'],
            ['p'],
        ];
    }

    /**
     * @dataProvider trueProvider
     * @param mixed $value
     */
    public function testPayloadTrueWithStringHaystack($value)
    {
        $this->assertTrue($this->assertPayloadWithStringHaystack($value));
    }

    /**
     * @dataProvider falseProvider
     * @param mixed $value
     */
    public function testPayloadFalseWithStringHaystack($value)
    {
        $this->assertFalse($this->assertPayloadWithStringHaystack($value));
    }

    /**
     * @dataProvider trueProvider
     * @param mixed $value
     */
    public function testValueTrueWithStringHaystack($value)
    {
        $this->assertTrue($this->assertValueWithStringHaystack($value));
    }

    /**
     * @dataProvider falseProvider
     * @param mixed $value
     */
    public function testValueFalseWithStringHaystack($value)
    {
        $this->assertFalse($this->assertValueWithStringHaystack($value));
    }

    protected function assertPayloadWithStringHaystack($value)
    {
        $rule = $this->buildRuleWithStringHaystack();
        $payload = $this->buildPayload($value);
        $callback = function (\Altair\Middleware\Contracts\PayloadInterface $payload) {
            return $payload;
        };

        $payload =  call_user_func_array($rule, [$payload, $callback]);

        return $payload->getAttribute(PayloadInterface::ATTRIBUTE_RESULT) === true;
    }

    protected function assertValueWithStringHaystack($value)
    {
        $rule = $this->buildRuleWithStringHaystack();

        return $rule->assert($value);
    }

    protected function buildRule()
    {
        return new InRule(['val0', 'val1', 'key0' => 'val2', 'key1' => 'val3']);
    }

    protected function buildRuleWithStringHaystack()
    {
        return new InRule('val0, val1, key0, val2, key1, val3');
    }
}

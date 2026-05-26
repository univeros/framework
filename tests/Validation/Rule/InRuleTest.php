<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Contracts\PayloadInterface;
use Altair\Validation\Rule\InRule;

use PHPUnit\Framework\Attributes\DataProvider;
class InRuleTest extends AbstractRuleTest
{
    #[\Override]
    public static function trueProvider(): array
    {
        return [
            ['val0'],
            ['val1'],
            ['val3'],
        ];
    }

    #[\Override]
    public static function falseProvider(): array
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
     * @param mixed $value
     */
    #[DataProvider('trueProvider')]
    public function testPayloadTrueWithStringHaystack(string $value): void
    {
        $this->assertTrue($this->assertPayloadWithStringHaystack($value));
    }

    /**
     * @param mixed $value
     */
    #[DataProvider('falseProvider')]
    public function testPayloadFalseWithStringHaystack(int|string $value): void
    {
        $this->assertFalse($this->assertPayloadWithStringHaystack($value));
    }

    /**
     * @param mixed $value
     */
    #[DataProvider('trueProvider')]
    public function testValueTrueWithStringHaystack(string $value): void
    {
        $this->assertTrue($this->assertValueWithStringHaystack($value));
    }

    /**
     * @param mixed $value
     */
    #[DataProvider('falseProvider')]
    public function testValueFalseWithStringHaystack(int|string $value): void
    {
        $this->assertFalse($this->assertValueWithStringHaystack($value));
    }

    protected function assertPayloadWithStringHaystack($value): bool
    {
        $rule = $this->buildRuleWithStringHaystack();
        $payload = $this->buildPayload($value);
        $callback = fn(\Altair\Middleware\Contracts\PayloadInterface $payload): \Altair\Middleware\Contracts\PayloadInterface => $payload;

        $payload =  call_user_func_array($rule, [$payload, $callback]);

        return $payload->getAttribute(PayloadInterface::ATTRIBUTE_RESULT) === true;
    }

    protected function assertValueWithStringHaystack($value)
    {
        $rule = $this->buildRuleWithStringHaystack();

        return $rule->assert($value);
    }

    #[\Override]
    protected function buildRule(): InRule
    {
        return new InRule(['val0', 'val1', 'key0' => 'val2', 'key1' => 'val3']);
    }

    protected function buildRuleWithStringHaystack(): InRule
    {
        return new InRule('val0, val1, key0, val2, key1, val3');
    }
}

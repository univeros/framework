<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Middleware\Payload;
use Altair\Validation\Contracts\PayloadInterface;
use PHPUnit\Framework\TestCase;

use PHPUnit\Framework\Attributes\DataProvider;
abstract class AbstractRuleTest extends TestCase
{
    #[DataProvider('trueProvider')]
    public function testPayloadTrue(mixed $value): void
    {
        $this->assertTrue($this->assertPayload($value));
    }

    #[DataProvider('falseProvider')]
    public function testPayloadFalse(mixed $value): void
    {
        $this->assertFalse($this->assertPayload($value));
    }

    #[DataProvider('trueProvider')]
    public function testValueTrue(mixed $value): void
    {
        $this->assertTrue($this->assertValue($value));
    }

    #[DataProvider('falseProvider')]
    public function testValueFalse(mixed $value): void
    {
        $this->assertFalse($this->assertValue($value));
    }

    abstract public static function trueProvider();

    abstract public static function falseProvider();

    protected function assertPayload($value)
    {
        $rule = $this->buildRule();
        $payload = $this->buildPayload($value);
        $callback = fn(\Altair\Middleware\Contracts\PayloadInterface $payload): \Altair\Middleware\Contracts\PayloadInterface => $payload;

        $payload =  call_user_func_array($rule, [$payload, $callback]);

        return $payload->getAttribute(PayloadInterface::ATTRIBUTE_RESULT) === true;
    }

    protected function assertValue($value)
    {
        $rule = $this->buildRule();

        return $rule->assert($value);
    }

    protected function buildPayload($value)
    {
        $subject = [
            'test' => $value
        ];

        return (new Payload())
            ->withAttribute(PayloadInterface::ATTRIBUTE_SUBJECT, $subject)
            ->withAttribute(PayloadInterface::ATTRIBUTE_KEY, 'test');
    }

    abstract protected function buildRule();
}

<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Middleware\Payload;
use Altair\Validation\Contracts\PayloadInterface;
use PHPUnit\Framework\TestCase;

abstract class AbstractRuleTest extends TestCase
{
    /**
     * @dataProvider trueProvider
     * @param mixed $value
     */
    public function testPayloadTrue($value)
    {
        $this->assertTrue($this->assertPayload($value));
    }

    /**
     * @dataProvider falseProvider
     * @param mixed $value
     */
    public function testPayloadFalse($value)
    {
        $this->assertFalse($this->assertPayload($value));
    }

    /**
     * @dataProvider trueProvider
     * @param mixed $value
     */
    public function testValueTrue($value)
    {
        $this->assertTrue($this->assertValue($value));
    }

    /**
     * @dataProvider falseProvider
     * @param mixed $value
     */
    public function testValueFalse($value)
    {
        $this->assertFalse($this->assertValue($value));
    }

    abstract public function trueProvider();

    abstract public function falseProvider();

    protected function assertPayload($value)
    {
        $rule = $this->buildRule();
        $payload = $this->buildPayload($value);
        $callback = function (\Altair\Middleware\Contracts\PayloadInterface $payload) {
            return $payload;
        };

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

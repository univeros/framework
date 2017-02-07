<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Contracts\PayloadInterface;
use Altair\Validation\Payload;
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

        return $payload->getAttribute(PayloadInterface::RESULT_KEY) === true;
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
            ->withAttribute(PayloadInterface::SUBJECT_KEY, $subject)
            ->withAttribute(PayloadInterface::ATTRIBUTE_KEY, 'test');
    }

    abstract protected function buildRule();
}

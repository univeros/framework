<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Contracts\PayloadInterface;
use Altair\Validation\Exception\InvalidArgumentException;
use Altair\Validation\Payload;
use Altair\Validation\Rule\IpRule;
use PHPUnit\Framework\TestCase;

class IpRuleTest extends TestCase
{
    public function trueProvider()
    {
        return [
            ['127.0.0.1', null, '127.*'],
            ['127.0.0.1', null, '127.0.*'],
            ['127.0.0.1', null, '127.0.0.*'],
            ['192.168.2.6', null, '192.168.*.6'],
            ['192.168.2.6', null, '192.*.2.6'],
            ['10.168.2.6', null, '*.168.2.6'],
            ['192.168.2.6', null, '192.168.*.*'],
            ['192.10.2.6', null, '192.*.*.*'],
            ['192.168.255.156', null, '*'],
            ['192.168.255.156', null, '*.*.*.*'],
            ['127.0.0.1', null, '127.0.0.0-127.0.0.255'],
            ['192.168.2.6', null, '192.168.0.0-192.168.255.255'],
            ['192.10.2.6', null, '192.0.0.0-192.255.255.255'],
            ['192.168.255.156', null, '0.0.0.0-255.255.255.255'],
            ['220.78.173.2', null, '220.78.168/21'],
            ['220.78.173.2', null, '220.78.168.0/21'],
            ['220.78.173.2', null, '220.78.168.0/255.255.248.0'],
        ];
    }

    public function falseProvider()
    {
        return [
            [''],
            ['...'],
            ['j'],
            [' '],
            ['Foo'],
            ['192.168.0.1', FILTER_FLAG_NO_PRIV_RANGE],
        ];
    }

    public function invalidIpsForNetworkRangeProvider()
    {
        return [
            ['127.0.0.1', null, '127.0.1.*'],
            ['192.168.2.6', null, '192.163.*.*'],
            ['192.10.2.6', null, '193.*.*.*'],
            ['127.0.0.1', null, '127.0.1.0-127.0.1.255'],
            ['192.168.2.6', null, '192.163.0.0-192.163.255.255'],
            ['192.10.2.6', null, '193.168.0.0-193.255.255.255'],
            ['220.78.176.1', null, '220.78.168/21'],
            ['220.78.176.2', null, '220.78.168.0/21'],
            ['220.78.176.3', null, '220.78.168.0/255.255.248.0'],
        ];
    }

    public function invalidNetworkRangeProvider()
    {
        return [
            ['127.0.0.1', null, '127.0.1./*'],
            ['192.168.2.6', null, '192.163.*.*/kk'],
        ];
    }

    /**
     * @dataProvider trueProvider
     * @param mixed $value
     * @param null|mixed $options
     * @param null|mixed $range
     */
    public function testPayloadTrue($value, $options = null, $range = null)
    {
        $this->assertTrue($this->assertPayload($value, $options, $range));
    }

    /**
     * @dataProvider falseProvider
     * @param mixed $value
     * @param null|mixed $options
     * @param null|mixed $range
     */
    public function testPayloadFalse($value, $options = null, $range = null)
    {
        $this->assertFalse($this->assertPayload($value, $options, $range));
    }

    /**
     * @dataProvider trueProvider
     * @param mixed $value
     * @param null|mixed $options
     * @param null|mixed $range
     */
    public function testValueTrue($value, $options = null, $range = null)
    {
        $this->assertTrue($this->assertValue($value, $options, $range));
    }

    /**
     * @dataProvider falseProvider
     * @param mixed $value
     * @param null|mixed $options
     * @param null|mixed $range
     */
    public function testValueFalse($value, $options = null, $range = null)
    {
        $this->assertFalse($this->assertValue($value, $options, $range));
    }

    /**
     * @dataProvider invalidIpsForNetworkRangeProvider

     * @param $value
     * @param null $options
     * @param null $range
     */
    public function testInvalidIpsForRange($value, $options = null, $range = null)
    {
        $this->assertFalse($this->assertValue($value, $options, $range));
    }

    /**
     * @dataProvider invalidNetworkRangeProvider
     * @expectedException InvalidArgumentException

     * @param $value
     * @param null $options
     * @param null $range
     */
    public function testInvalidRange($value, $options = null, $range = null)
    {
        $this->assertValue($value, $options, $range);
    }

    protected function assertPayload($value, $options = null, $range = null)
    {
        $rule = $this->buildRule($options, $range);
        $payload = $this->buildPayload($value);
        $callback = function (\Altair\Middleware\Contracts\PayloadInterface $payload) {
            return $payload;
        };

        $payload =  call_user_func_array($rule, [$payload, $callback]);

        return $payload->getAttribute(PayloadInterface::ATTRIBUTE_RESULT) === true;
    }

    protected function assertValue($value, $options = null, $range = null)
    {
        $rule = $this->buildRule($options, $range);

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

    protected function buildRule($options = null, $range = null)
    {
        return new IpRule($options, $range);
    }
}

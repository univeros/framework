<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Middleware\Payload;
use Altair\Validation\Contracts\PayloadInterface;
use Altair\Validation\Exception\InvalidArgumentException;
use Altair\Validation\Rule\IpRule;
use PHPUnit\Framework\TestCase;

use PHPUnit\Framework\Attributes\DataProvider;
class IpRuleTest extends TestCase
{
    public static function trueProvider(): array
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

    public static function falseProvider(): array
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

    public static function invalidIpsForNetworkRangeProvider(): array
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

    public static function invalidNetworkRangeProvider(): array
    {
        return [
            ['127.0.0.1', null, '127.0.1./*'],
            ['192.168.2.6', null, '192.163.*.*/kk'],
        ];
    }

    /**
     * @param mixed $value
     * @param null|mixed $options
     * @param null|mixed $range
     */
    #[DataProvider('trueProvider')]
    public function testPayloadTrue(string $value, $options = null, string $range = null): void
    {
        $this->assertTrue($this->assertPayload($value, $options, $range));
    }

    /**
     * @param mixed $value
     * @param null|mixed $options
     * @param null|mixed $range
     */
    #[DataProvider('falseProvider')]
    public function testPayloadFalse(string $value, int $options = null, $range = null): void
    {
        $this->assertFalse($this->assertPayload($value, $options, $range));
    }

    /**
     * @param mixed $value
     * @param null|mixed $options
     * @param null|mixed $range
     */
    #[DataProvider('trueProvider')]
    public function testValueTrue(string $value, $options = null, string $range = null): void
    {
        $this->assertTrue($this->assertValue($value, $options, $range));
    }

    /**
     * @param mixed $value
     * @param null|mixed $options
     * @param null|mixed $range
     */
    #[DataProvider('falseProvider')]
    public function testValueFalse(string $value, int $options = null, $range = null): void
    {
        $this->assertFalse($this->assertValue($value, $options, $range));
    }

    /**
     * @param $value
     */
    #[DataProvider('invalidIpsForNetworkRangeProvider')]
    public function testInvalidIpsForRange(string $value, $options = null, string $range = null): void
    {
        $this->assertFalse($this->assertValue($value, $options, $range));
    }

    public function testInvalidRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->assertValue('192.168.1.1', FILTER_FLAG_IPV4, 'not-a-valid-range');
    }

    protected function assertPayload($value, $options = null, $range = null): bool
    {
        $rule = $this->buildRule($options, $range);
        $payload = $this->buildPayload($value);
        $callback = fn(\Altair\Middleware\Contracts\PayloadInterface $payload): \Altair\Middleware\Contracts\PayloadInterface => $payload;

        $payload =  call_user_func_array($rule, [$payload, $callback]);

        return $payload->getAttribute(PayloadInterface::ATTRIBUTE_RESULT) === true;
    }

    protected function assertValue($value, $options = null, $range = null): bool
    {
        $rule = $this->buildRule($options, $range);

        return $rule->assert($value);
    }

    protected function buildPayload($value): \Altair\Middleware\Contracts\PayloadInterface
    {
        $subject = [
            'test' => $value
        ];

        return (new Payload())
            ->withAttribute(PayloadInterface::ATTRIBUTE_SUBJECT, $subject)
            ->withAttribute(PayloadInterface::ATTRIBUTE_KEY, 'test');
    }

    protected function buildRule($options = null, $range = null): IpRule
    {
        return new IpRule($options, $range);
    }
}

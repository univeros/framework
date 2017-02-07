<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Contracts\PayloadInterface;
use Altair\Validation\Payload;
use Altair\Validation\Rule\IsbnRule;
use PHPUnit\Framework\TestCase;

class IsbnRuleTest extends TestCase
{
    public function trueProvider()
    {
        return [
            // from -ronanguilloux/IsoCodes package
            ['8881837188', 10],
            ['2266111566', 10],
            ['2123456802', 10],
            ['888 18 3 7 1-88', 10],
            ['2-7605-1028-X', 10],
            ['978-88-8183-718-2', 13],
            ['978-2-266-11156-0', 13],
            ['978-2-12-345680-3', 13],
            ['978-88-8183-718-2', 13],
            ['978-2-7605-1028-9', 13],
            ['2112345678900', 13],
            // Same but with 'both' option
            ['8881837188'],
            ['2266111566'],
            ['2123456802'],
            ['888 18 3 7 1-88'],
            ['2-7605-1028-X'],
            ['978-88-8183-718-2'],
            ['978-2-266-11156-0'],
            ['978-2-12-345680-3'],
            ['978-88-8183-718-2'],
            ['978-2-7605-1028-9'],
            ['2112345678900'],
        ];
    }

    public function falseProvider()
    {
        return [
            // from -ronanguilloux/IsoCodes package
            ['8881837187'],
            ['888183718A'],
            ['stringof10'],
            [888183718],       // not a string
            [88818371880],     // not 10 chars found
            ['88818371880'],   // not 10 chars found
            ['8881837188A'],   // not numeric-only
            ['8881837189'],    // bad checksum digit
            // Valid ISBN-10 but not ISBN-13
            ['8881837188', 13],
            ['2266111566', 13],
            ['2123456802', 13],
            ['888 18 3 7 1-88', 13],
            ['2-7605-1328-X', 13],
            // Valid ISBN-13 but not ISBN-10
            ['978-88-8183-718-2', 10],
            ['978-2-266-11156-0', 10],
            ['978-2-12-345680-3', 10],
            ['978-88-8183-718-2', 10],
            ['978-2-7605-1028-9', 10],
            ['2112345678900', 10],
        ];
    }

    /**
     * @dataProvider trueProvider
     * @param mixed $value
     * @param null|mixed $type
     */
    public function testPayloadTrue($value, $type = null)
    {
        $this->assertTrue($this->assertPayload($value, $type));
    }

    /**
     * @dataProvider falseProvider
     * @param mixed $value
     * @param null|mixed $type
     */
    public function testPayloadFalse($value, $type = null)
    {
        $this->assertFalse($this->assertPayload($value, $type));
    }

    /**
     * @dataProvider trueProvider
     * @param mixed $value
     * @param null|mixed $type
     */
    public function testValueTrue($value, $type = null)
    {
        $this->assertTrue($this->assertValue($value, $type));
    }

    /**
     * @dataProvider falseProvider
     * @param mixed $value
     * @param null|mixed $type
     */
    public function testValueFalse($value, $type = null)
    {
        $this->assertFalse($this->assertValue($value, $type));
    }

    protected function assertPayload($value, $type = null)
    {
        $rule = $this->buildRule($type);
        $payload = $this->buildPayload($value);
        $callback = function (\Altair\Middleware\Contracts\PayloadInterface $payload) {
            return $payload;
        };

        $payload =  call_user_func_array($rule, [$payload, $callback]);

        return $payload->getAttribute(PayloadInterface::ATTRIBUTE_RESULT) === true;
    }

    protected function assertValue($value, $type = null)
    {
        $rule = $this->buildRule($type);

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

    protected function buildRule($type = null)
    {
        return new IsbnRule($type);
    }
}

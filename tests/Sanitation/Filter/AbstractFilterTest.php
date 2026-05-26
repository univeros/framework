<?php
namespace Altair\Tests\Sanitation\Filter;

use Altair\Middleware\Payload;
use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Contracts\PayloadInterface;
use PHPUnit\Framework\TestCase;

abstract class AbstractFilterTest extends TestCase
{
    /**
     * @dataProvider dataProvider
     */
    public function testFilteredPayload(mixed $value, mixed $expected): void
    {
        $this->assertTrue($this->parsePayload($value, $expected));
    }

    /**
     * @dataProvider dataProvider
     */
    public function testFilteredValue(mixed $value, mixed $expected): void
    {
        $this->assertTrue($this->parseValue($value, $expected));
    }

    abstract public static function dataProvider();

    protected function parsePayload($value, $expected)
    {
        $rule = $this->buildFilter();
        $payload = $this->buildPayload($value);

        $callback = fn(\Altair\Middleware\Contracts\PayloadInterface $payload): \Altair\Middleware\Contracts\PayloadInterface => $payload;

        $payload =  call_user_func_array($rule, [$payload, $callback]);

        $subject = $payload->getAttribute(PayloadInterface::ATTRIBUTE_SUBJECT);

        $this->assertEquals($expected, $subject->test);

        return $subject->test === $expected;
    }

    protected function parseValue($value, $expected)
    {
        $filter = $this->buildFilter();
        $this->assertEquals($expected, $filter->parse($value));
        return $filter->parse($value) === $expected;
    }

    protected function buildPayload($value)
    {
        $subject = [
            'test' => $value
        ];

        return (new Payload())
            ->withAttribute(PayloadInterface::ATTRIBUTE_SUBJECT, $subject)
            ->withAttribute(PayloadInterface::ATTRIBUTE_KEY, 'test')
            ->withAttribute('test', $value); // its built when using Sanitizer with the values of the subject
    }

    abstract protected function buildFilter(): FilterInterface;
}

<?php
namespace Altair\Tests\Validation\Rule;

use Altair\Validation\Contracts\PayloadInterface;
use Altair\Validation\Payload;
use Altair\Validation\Rule\CreditCardRule;
use PHPUnit\Framework\TestCase;

class CreditCardRuleTest extends TestCase
{
    public function trueProvider()
    {
        return [
            ['visaelectron', ['4917300800000000', '4913183913639755', '4917558220925391', '4026433620435706']],
            ['carteblanche', ['30052526839118', '30294073805171', '30521225806875']],
            ['maestro', ['6759649826438453', '6799990100000000019', '6304656189339285', '6763453611158861']],
            ['forbrugsforeningen', ['6007220000000004']],
            ['dankort', ['5019717010103742']],
            ['visa', ['4111111111111111', '4532735892324492', '4916144373576959']],
            ['mastercard', ['5181215988247643', '5199699592637820', '5212190159043209']],
            ['amex', ['340628313592880', '342572010687288', '377062809840882', '378282246310005']],
            ['dinersclub', ['36030958438041', '36096039698259', '36700102000000', '36148900647913']],
            ['discover', ['6011269591073940', '6011534167975624', '6011727926699390']],
            ['unionpay', [ '6271136264806203568','6236265930072952775','6204679475679144515','6216657720782466507',]],
            ['jcb', ['3530111333300000','3566002020360505']],
            ['solo', ['6334 5898 9800 0001', '6767 8200 9988 0077 06', '6334 9711 1111 1114']],
            ['switch', ['6331101999990016']]
        ];
    }

    public function falseProvider()
    {
        return [
            ['visaelectron', ['30052526839118', '30294073805171', '30521225806875']],
            ['carteblanche', ['4917300800000000', '4913183913639755', '4917558220925391', '4026433620435706']],
            ['maestro', ['340628313592880', '342572010687288', '377062809840882', '378282246310005']],
            ['forbrugsforeningen', ['6759649826438453', '6799990100000000019', '6304656189339285', '6763453611158861']],
            ['dankort', ['501971709839393939310103742']],
            ['visa', ['88Akdk////8888']],
            ['mastercard', ['374747474747474']],
            ['amex', ['6007220000000004']],
            ['dinersclub', ['6011269591073940', '6011534167975624', '6011727926699390']],
            ['discover', ['36030958438041', '36096039698259', '36700102000000', '36148900647913']],
            ['unionpay',['3530111333300000','3566002020360505']],
            ['jcb',  [ '6271136264806203568','6236265930072952775','6204679475679144515','6216657720782466507',]],
            ['solo',['6331101999990016']],
            ['switch', ['6334 5898 9800 0001', '6767 8200 9988 0077 06', '6334 9711 1111 1114'] ]
        ];
    }

    /**
     * @dataProvider trueProvider
     * @param $type
     * @param $values
     */
    public function testValidCards($type, $values)
    {
        $rule = new CreditCardRule($type);
        foreach ($values as $value) {
            $this->assertTrue($this->assertPayload($rule, $value));
            $this->assertTrue($this->assertValue($rule, $value));
        }
    }

    /**
     * @dataProvider falseProvider
     * @param $type
     * @param $values
     */
    public function testInvalidCards($type, $values)
    {
        $rule = new CreditCardRule($type);
        foreach ($values as $value) {
            $this->assertFalse($this->assertPayload($rule, $value));
            $this->assertFalse($this->assertValue($rule, $value));
        }
    }

    protected function assertPayload($rule, $value)
    {
        $payload = $this->buildPayload($value);
        $callback = function (\Altair\Middleware\Contracts\PayloadInterface $payload) {
            return $payload;
        };

        $payload =  call_user_func_array($rule, [$payload, $callback]);

        return $payload->getAttribute(PayloadInterface::RESULT_KEY) === true;
    }

    protected function assertValue($rule, $value)
    {
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
}

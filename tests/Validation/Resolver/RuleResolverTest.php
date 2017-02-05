<?php
namespace Altair\Tests\Validation\Resolver;

use Altair\Container\Container;
use Altair\Validation\Contracts\RuleInterface;
use Altair\Validation\Resolver\RuleResolver;
use Altair\Validation\Rule\AlphaRule;

class RuleResolverTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider rulesProvider
     * @param mixed $entry
     */
    public function testResolver($entry)
    {
        $resolver = $this->getResolver();
        $rule = call_user_func($resolver, $entry);

        $this->assertTrue($rule instanceof RuleInterface);
    }

    public function rulesProvider()
    {
        return [
            [AlphaRule::class],
            [['class' => AlphaRule::class]],
            // todo: add rule option with multiple arguments
        ];
    }
    protected function getResolver()
    {
        return new RuleResolver(new Container());
    }
}

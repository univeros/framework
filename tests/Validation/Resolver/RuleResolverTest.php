<?php
namespace Altair\Tests\Validation\Resolver;

use Altair\Container\Container;
use Altair\Validation\Contracts\RuleInterface;
use Altair\Validation\Resolver\RuleResolver;
use Altair\Validation\Rule\AlphaRule;
use PHPUnit\Framework\TestCase;

class RuleResolverTest extends TestCase
{
    /**
     * @dataProvider rulesProvider
     * @param mixed $entry
     */
    public function testResolver(string|array $entry): void
    {
        $resolver = $this->getResolver();
        $rule = call_user_func($resolver, $entry);

        $this->assertTrue($rule instanceof RuleInterface);
    }

    public static function rulesProvider(): array
    {
        return [
            [AlphaRule::class],
            [['class' => AlphaRule::class]],
            // todo: add rule option with multiple arguments
        ];
    }

    protected function getResolver(): RuleResolver
    {
        return new RuleResolver(new Container());
    }
}

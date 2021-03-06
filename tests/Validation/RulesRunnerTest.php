<?php
namespace Altair\Tests\Validation;

use Altair\Container\Container;
use Altair\Middleware\Payload;
use Altair\Structure\Queue;
use Altair\Validation\Contracts\PayloadInterface;
use Altair\Validation\Resolver\RuleResolver;
use Altair\Validation\Rule\AlphaRule;
use Altair\Validation\RulesRunner;
use PHPUnit\Framework\TestCase;

class RulesRunnerTest extends TestCase
{
    public function testRunner()
    {
        $runner = $this->getRulesRunner();
        $payload = call_user_func($runner, $this->getPayload());
        $this->assertEquals('A passed', $payload->getAttribute(RuleA::class));
        $this->assertEquals('B passed', $payload->getAttribute(RuleB::class));
        $this->assertTrue($payload->getAttribute(PayloadInterface::ATTRIBUTE_RESULT) === true);

        $runner->withRules([RuleA::class, RuleB::class]);

        $payload = call_user_func($runner, $this->getPayload());
        $this->assertEquals('A passed', $payload->getAttribute(RuleA::class));
        $this->assertEquals('B passed', $payload->getAttribute(RuleB::class));
    }

    protected function getRulesRunner()
    {
        $queue = new Queue(
            [
                AlphaRule::class,
                ['class' => RuleA::class],
                ['class' => RuleB::class],
            ]
        );

        $resolver = new RuleResolver(new Container());

        return new RulesRunner($resolver, $queue);
    }

    protected function getPayload()
    {
        return (new Payload())
            ->withAttribute(PayloadInterface::ATTRIBUTE_SUBJECT, ['test' => 'alphaOnly'])
            ->withAttribute(PayloadInterface::ATTRIBUTE_KEY, 'test');
    }
}

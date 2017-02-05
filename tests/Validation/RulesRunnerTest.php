<?php
namespace Altair\Tests\Validation;

use Altair\Container\Container;
use Altair\Structure\Queue;
use Altair\Validation\Contracts\PayloadInterface;
use Altair\Validation\Payload;
use Altair\Validation\Resolver\RuleResolver;
use Altair\Validation\Rule\AlphaRule;
use Altair\Validation\RulesRunner;

class RulesRunnerTest extends \PHPUnit_Framework_TestCase
{
    public function testRunner()
    {
        $runner = $this->getRulesRunner();
        $payload = call_user_func($runner, $this->getPayload());
        $this->assertEquals('A passed', $payload->getAttribute(RuleA::class));
        $this->assertEquals('B passed', $payload->getAttribute(RuleB::class));
        $this->assertTrue($payload->getAttribute(PayloadInterface::RESULT_KEY) === true);

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
            ->withAttribute(PayloadInterface::SUBJECT_KEY, ['test' => 'alphaOnly'])
            ->withAttribute(PayloadInterface::ATTRIBUTE_KEY, 'test');
    }
}

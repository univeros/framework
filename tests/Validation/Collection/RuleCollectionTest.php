<?php
namespace Altair\Tests\Validation\Collection;


use Altair\Tests\Validation\RuleA;
use Altair\Tests\Validation\ValidEntity;
use Altair\Validation\Collection\RuleCollection;
use Altair\Validation\Exception\InvalidArgumentException;
use Altair\Validation\Rule\AlphaNumRule;
use PHPUnit\Framework\TestCase;

class RuleCollectionTest extends TestCase
{

    public function testValidRules()
    {
        $entity = new ValidEntity();

        $ruleCollection = $entity->getRules();

        $this->assertTrue($ruleCollection->hasKey('firstName'));
        $this->assertTrue($ruleCollection->hasKey('firstName, lastName'));

        $ruleCollection->putAll(
            [
                'testAttribute' => ['class' => AlphaNumRule::class]
            ]
        );

        $this->assertTrue($ruleCollection->hasKey('testAttribute'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testExceptionsWithClassNotImplementingRuleInterface()
    {
        $ruleCollection = new RuleCollection([]);
        $ruleCollection->put('fail', ValidEntity::class);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testExceptionsWithWrongConfigurationNoClassKey()
    {
        $ruleCollection = new RuleCollection();
        $ruleCollection->put('fail', [[RuleA::class]]);
    }
}

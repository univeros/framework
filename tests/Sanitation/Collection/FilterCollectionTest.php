<?php
namespace Altair\Tests\Sanitation\Collection;

use Altair\Sanitation\Collection\FilterCollection;
use Altair\Sanitation\Exception\InvalidArgumentException;
use Altair\Sanitation\Filter\AlphaNumFilter;
use Altair\Tests\Sanitation\FilterA;
use Altair\Tests\Sanitation\SanitizableEntity;
use PHPUnit\Framework\TestCase;

class FilterCollectionTest extends TestCase
{
    public function testValidFilters()
    {
        $entity = new SanitizableEntity();

        $ruleCollection = $entity->getFilters();

        $this->assertTrue($ruleCollection->hasKey('alpha'));
        $this->assertTrue($ruleCollection->hasKey('alphaNum'));

        $ruleCollection->putAll(
            [
                'alphaNum' => ['class' => AlphaNumFilter::class]
            ]
        );

        $this->assertTrue($ruleCollection->hasKey('alphaNum'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testExceptionsWithClassNotImplementingRuleInterface()
    {
        $ruleCollection = new FilterCollection([]);
        $ruleCollection->put('fail', SanitizableEntity::class);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testExceptionsWithWrongConfigurationNoClassKey()
    {
        $ruleCollection = new FilterCollection();
        $ruleCollection->put('fail', [[FilterA::class]]);
    }
}

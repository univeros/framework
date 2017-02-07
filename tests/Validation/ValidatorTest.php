<?php
namespace Altair\Tests\Validation;

use Altair\Container\Container;
use Altair\Middleware\Contracts\PayloadInterface;
use Altair\Validation\Resolver\RuleResolver;
use Altair\Validation\RulesRunner;
use Altair\Validation\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    public function testValidEntity()
    {
        $validator = $this->getValidator();

        $entity = new ValidEntity();

        $this->assertTrue($validator->validate($entity));
    }

    public function testInvalidEntity()
    {
        $validator = $this->getValidator();

        $entity = new InvalidEntity();

        $this->assertFalse($validator->validate($entity));

        $payload = $validator->getPayload();
        $this->assertTrue($payload instanceof PayloadInterface);

        $failures = $payload->getAttribute(\Altair\Validation\Contracts\PayloadInterface::ATTRIBUTE_FAILURES);
        $this->assertTrue(is_array($failures));
        $this->assertCount(2, $failures);
        $this->assertEquals('"4nt0n10" have invalid alphabetic character(s)', $failures['firstName']);
        $this->assertEquals('"4alias" have invalid alphabetic character(s)', $failures['alias']);
    }

    protected function getValidator()
    {
        return new Validator(new RulesRunner(new RuleResolver(new Container())));
    }
}

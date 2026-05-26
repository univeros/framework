<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Spec;

use Altair\Scaffold\Exception\SpecValidationException;
use Altair\Scaffold\Spec\Ast\DomainSpec;
use Altair\Scaffold\Spec\Ast\EndpointSpec;
use Altair\Scaffold\Spec\Ast\InputFieldSpec;
use Altair\Scaffold\Spec\Ast\OutputResponseSpec;
use Altair\Scaffold\Spec\Ast\Spec;
use Altair\Scaffold\Spec\Validator;
use Altair\Tests\Scaffold\Support\SpecFixture;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testValidSpecYieldsNoErrors(): void
    {
        self::assertSame([], (new Validator())->collectErrors(SpecFixture::createUser()));
    }

    public function testUnknownHttpMethodReported(): void
    {
        $spec = new Spec(
            endpoint: new EndpointSpec('SUBSCRIBE', '/foo', 'x', []),
            inputs: [],
            outputs: [],
            domain: new DomainSpec('App\\Foo'),
        );

        $errors = (new Validator())->collectErrors($spec);

        self::assertNotEmpty($errors);
        self::assertStringContainsString('SUBSCRIBE', $errors[0]);
    }

    public function testUnknownValidationRuleReported(): void
    {
        $spec = new Spec(
            endpoint: new EndpointSpec('GET', '/foo', '', []),
            inputs: [new InputFieldSpec('x', 'string', ['definitely-not-a-rule'])],
            outputs: [],
            domain: new DomainSpec('App\\Foo'),
        );

        $errors = (new Validator())->collectErrors($spec);

        self::assertNotEmpty($errors);
        self::assertStringContainsString('definitely-not-a-rule', $errors[0]);
    }

    public function testNonClassDomainReported(): void
    {
        $spec = new Spec(
            endpoint: new EndpointSpec('GET', '/foo', '', []),
            inputs: [],
            outputs: [],
            domain: new DomainSpec('not_a_class'),
        );

        $errors = (new Validator())->collectErrors($spec);

        self::assertNotEmpty($errors);
        self::assertStringContainsString('domain.class', $errors[0]);
    }

    public function testInvalidStatusCodeReported(): void
    {
        $spec = new Spec(
            endpoint: new EndpointSpec('GET', '/foo', '', []),
            inputs: [],
            outputs: [new OutputResponseSpec(999, [])],
            domain: new DomainSpec('App\\Foo'),
        );

        $errors = (new Validator())->collectErrors($spec);

        self::assertNotEmpty($errors);
    }

    public function testAssertValidThrowsOnError(): void
    {
        $this->expectException(SpecValidationException::class);
        (new Validator())->assertValid(new Spec(
            endpoint: new EndpointSpec('SUBSCRIBE', 'no-slash', '', []),
            inputs: [],
            outputs: [],
            domain: new DomainSpec('App\\Foo'),
        ));
    }
}

<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Spec;

use Altair\Scaffold\Exception\SpecParseException;
use Altair\Scaffold\Exception\SpecValidationException;
use Altair\Scaffold\Spec\Ast\IdempotencySpec;
use Altair\Scaffold\Spec\Parser;
use Altair\Scaffold\Spec\Validator;
use PHPUnit\Framework\TestCase;

final class IdempotencyParserTest extends TestCase
{
    public function testIdempotencyBlockParses(): void
    {
        $yaml = <<<'YAML'
            endpoint:
              method: post
              path: /users
              tags: [users]
            domain:
              class: App\User\CreateUser
            idempotency:
              ttl: 24h
              scope: tenant
              mode: required
            YAML;

        $spec = (new Parser())->parseString($yaml);

        self::assertTrue($spec->hasIdempotency());
        self::assertInstanceOf(IdempotencySpec::class, $spec->idempotency);
        self::assertSame('24h', $spec->idempotency->ttl);
        self::assertSame('tenant', $spec->idempotency->scope);
        self::assertSame('required', $spec->idempotency->mode);
    }

    public function testIdempotencyDefaultsScopeAndMode(): void
    {
        $yaml = <<<'YAML'
            endpoint:
              method: post
              path: /users
              tags: []
            domain:
              class: App\User\CreateUser
            idempotency:
              ttl: 1h
            YAML;

        $spec = (new Parser())->parseString($yaml);

        self::assertInstanceOf(IdempotencySpec::class, $spec->idempotency);
        self::assertSame('tenant', $spec->idempotency->scope);
        self::assertSame('optional', $spec->idempotency->mode);
    }

    public function testIdempotencyMissingTtlRaises(): void
    {
        $yaml = <<<'YAML'
            endpoint:
              method: post
              path: /users
              tags: []
            domain:
              class: App\User\CreateUser
            idempotency:
              scope: tenant
            YAML;

        $this->expectException(SpecParseException::class);
        $this->expectExceptionMessage('idempotency.ttl');
        (new Parser())->parseString($yaml);
    }

    public function testIdempotencyAbsentLeavesNullAndHasIdempotencyFalse(): void
    {
        $yaml = <<<'YAML'
            endpoint:
              method: post
              path: /users
              tags: []
            domain:
              class: App\User\CreateUser
            YAML;

        $spec = (new Parser())->parseString($yaml);

        self::assertFalse($spec->hasIdempotency());
        self::assertNull($spec->idempotency);
    }

    public function testValidatorRejectsBadTtlPattern(): void
    {
        $yaml = <<<'YAML'
            endpoint:
              method: post
              path: /users
              tags: []
            domain:
              class: App\User\CreateUser
            idempotency:
              ttl: forever
            YAML;

        $spec = (new Parser())->parseString($yaml);

        $errors = (new Validator())->collectErrors($spec);

        self::assertNotEmpty($errors);
        $matched = array_filter($errors, static fn(string $e): bool => str_contains($e, 'idempotency.ttl'));
        self::assertNotEmpty($matched);
    }

    public function testValidatorRejectsBadMode(): void
    {
        $yaml = <<<'YAML'
            endpoint:
              method: post
              path: /users
              tags: []
            domain:
              class: App\User\CreateUser
            idempotency:
              ttl: 24h
              mode: maybe
            YAML;

        $spec = (new Parser())->parseString($yaml);

        $errors = (new Validator())->collectErrors($spec);

        $matched = array_filter($errors, static fn(string $e): bool => str_contains($e, 'idempotency.mode'));
        self::assertNotEmpty($matched);
    }

    public function testValidatorAcceptsAllTtlUnits(): void
    {
        $validator = new Validator();
        $parser = new Parser();
        foreach (['100ms', '30s', '5m', '24h', '7d'] as $ttl) {
            $yaml = <<<YAML
                endpoint:
                  method: post
                  path: /users
                  tags: []
                domain:
                  class: App\\User\\CreateUser
                idempotency:
                  ttl: {$ttl}
                YAML;

            $spec = $parser->parseString($yaml);
            $errors = $validator->collectErrors($spec);
            $ttlErrors = array_filter($errors, static fn(string $e): bool => str_contains($e, 'idempotency.ttl'));
            self::assertSame([], $ttlErrors, sprintf("ttl='%s' should be accepted", $ttl));
        }
    }

    public function testValidatorAcceptsValidBlock(): void
    {
        $yaml = <<<'YAML'
            endpoint:
              method: post
              path: /users
              tags: []
            domain:
              class: App\User\CreateUser
            idempotency:
              ttl: 24h
              scope: tenant
              mode: required
            YAML;

        $spec = (new Parser())->parseString($yaml);

        (new Validator())->assertValid($spec);

        $this->expectNotToPerformAssertions();
    }

    public function testValidatorRaisesAggregatedExceptionOnFailure(): void
    {
        $yaml = <<<'YAML'
            endpoint:
              method: post
              path: /users
              tags: []
            domain:
              class: App\User\CreateUser
            idempotency:
              ttl: bogus
              mode: nope
            YAML;

        $spec = (new Parser())->parseString($yaml);

        $this->expectException(SpecValidationException::class);
        (new Validator())->assertValid($spec);
    }
}

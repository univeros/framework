<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Spec;

use Altair\Scaffold\Exception\SpecParseException;
use Altair\Scaffold\Spec\Parser;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    public function testParsesEndpointInputOutputAndDomain(): void
    {
        $yaml = <<<'YAML'
            endpoint:
              method: post
              path: /users
              summary: Create a new user
              tags: [users]
            input:
              email:
                type: string
                rules: [email, required]
              password:
                type: string
                rules: [min:8, required]
                sensitive: true
              role:
                type: enum
                of: App\User\Role
                default: Member
            output:
              201:
                body:
                  user: App\User\User
              422:
                body:
                  errors: array<string, list<string>>
            domain:
              class: App\User\CreateUser
              invocation: __invoke
            YAML;

        $spec = (new Parser())->parseString($yaml);

        self::assertSame('POST', $spec->endpoint->method);
        self::assertSame('/users', $spec->endpoint->path);
        self::assertSame(['users'], $spec->endpoint->tags);

        self::assertCount(3, $spec->inputs);
        self::assertSame('email', $spec->inputs[0]->name);
        self::assertTrue($spec->inputs[1]->sensitive);
        self::assertTrue($spec->inputs[2]->isEnum());
        self::assertSame('Member', $spec->inputs[2]->default);
        self::assertTrue($spec->inputs[2]->hasDefault);

        self::assertCount(2, $spec->outputs);
        self::assertSame(201, $spec->outputs[0]->status);
        self::assertSame('App\\User\\User', $spec->outputs[0]->body['user']);

        self::assertSame('App\\User\\CreateUser', $spec->domain->class);
    }

    public function testMissingEndpointKeyRaises(): void
    {
        $this->expectException(SpecParseException::class);
        (new Parser())->parseString("input: {}\ndomain:\n  class: A\\B");
    }

    public function testMalformedYamlRaises(): void
    {
        $this->expectException(SpecParseException::class);
        (new Parser())->parseString(":\n  - bad");
    }

    public function testParseFileMissingPathRaises(): void
    {
        $this->expectException(SpecParseException::class);
        (new Parser())->parseFile('/does/not/exist.yaml');
    }
}

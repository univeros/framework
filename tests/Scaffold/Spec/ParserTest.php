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

    public function testParsesNestedObjectAndArrayOfObjectInputs(): void
    {
        $yaml = <<<'YAML'
            endpoint: { method: POST, path: /pets, summary: Create pet }
            input:
              category:
                type: object
                rules: [required]
                fields:
                  id: { type: int }
                  name: { type: string, rules: [required] }
              tags:
                type: array
                fields:
                  id: { type: int }
            domain: { class: App\Pet\CreatePet }
            YAML;

        $spec = (new Parser())->parseString($yaml);

        self::assertCount(2, $spec->inputs);

        $category = $spec->inputs[0];
        self::assertSame('category', $category->name);
        self::assertTrue($category->isObject());
        self::assertTrue($category->isRequired());
        self::assertCount(2, $category->fields);
        self::assertSame('id', $category->fields[0]->name);
        self::assertSame('int', $category->fields[0]->type);
        self::assertTrue($category->fields[1]->isRequired());

        $tags = $spec->inputs[1];
        self::assertTrue($tags->isArrayOfObjects());
        self::assertSame('id', $tags->fields[0]->name);
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

    public function testQueueBlockParses(): void
    {
        $yaml = <<<'YAML'
            endpoint:
              method: post
              path: /users
              tags: [users]
            domain:
              class: App\User\CreateUser
            queue:
              on_create:
                message: App\Messages\SendWelcomeEmail
                fields:
                  user_id: string
                  email: string
                transport: default
            YAML;

        $spec = (new Parser())->parseString($yaml);

        self::assertCount(1, $spec->queue);
        self::assertSame('on_create', $spec->queue[0]->name);
        self::assertSame('App\\Messages\\SendWelcomeEmail', $spec->queue[0]->message);
        self::assertSame('default', $spec->queue[0]->transport);
        self::assertSame(['user_id' => 'string', 'email' => 'string'], $spec->queue[0]->fields);
    }

    public function testQueueEntryMissingMessageRaises(): void
    {
        $yaml = <<<'YAML'
            endpoint:
              method: post
              path: /users
              tags: []
            domain:
              class: App\User\CreateUser
            queue:
              on_create:
                fields:
                  user_id: string
            YAML;

        $this->expectException(SpecParseException::class);
        (new Parser())->parseString($yaml);
    }
}

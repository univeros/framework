<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Spec;

use Altair\Scaffold\Spec\Parser;
use PHPUnit\Framework\TestCase;

final class PersistenceParserTest extends TestCase
{
    public function testParsesPersistenceBlock(): void
    {
        $yaml = <<<'YAML'
            endpoint:
              method: post
              path: /users
              summary: Create user
              tags: [users]
            input:
              email:
                type: string
                rules: [email, required]
            domain:
              class: App\User\CreateUser
            persistence:
              entity:
                class: App\User\User
                table: users
                fields:
                  id:    { type: uuid, primary: true }
                  email: { type: string, unique: true }
                  password_hash: { type: string }
                  created_at: { type: datetime, default: now }
              repository: App\User\UserRepository
            YAML;

        $spec = (new Parser())->parseString($yaml);

        self::assertNotNull($spec->persistence);
        self::assertTrue($spec->hasPersistence());
        self::assertSame('App\\User\\User', $spec->persistence->entity->class);
        self::assertSame('users', $spec->persistence->entity->table);
        self::assertCount(4, $spec->persistence->entity->fields);

        $primary = $spec->persistence->entity->primaryField();
        self::assertNotNull($primary);
        self::assertSame('id', $primary->name);
        self::assertTrue($primary->primary);

        self::assertTrue($spec->persistence->entity->fields[1]->unique);
        self::assertSame('now', $spec->persistence->entity->fields[3]->default);
        self::assertTrue($spec->persistence->entity->fields[3]->hasDefault);

        self::assertSame('App\\User\\UserRepository', $spec->persistence->repository);
    }

    public function testParsesSpecWithoutPersistence(): void
    {
        $yaml = <<<'YAML'
            endpoint:
              method: get
              path: /health
              summary: Health check
              tags: []
            domain:
              class: App\Health\Check
            YAML;

        $spec = (new Parser())->parseString($yaml);

        self::assertNull($spec->persistence);
        self::assertFalse($spec->hasPersistence());
    }
}

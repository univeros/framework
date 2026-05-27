<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Spec;

use Altair\Scaffold\Spec\Ast\DomainSpec;
use Altair\Scaffold\Spec\Ast\EndpointSpec;
use Altair\Scaffold\Spec\Ast\PersistenceEntitySpec;
use Altair\Scaffold\Spec\Ast\PersistenceFieldSpec;
use Altair\Scaffold\Spec\Ast\PersistenceSpec;
use Altair\Scaffold\Spec\Ast\Spec;
use Altair\Scaffold\Spec\Validator;
use PHPUnit\Framework\TestCase;

final class PersistenceValidatorTest extends TestCase
{
    public function testValidPersistenceSpecHasNoErrors(): void
    {
        $errors = (new Validator())->collectErrors($this->spec([
            new PersistenceFieldSpec(name: 'id', type: 'uuid', primary: true),
            new PersistenceFieldSpec(name: 'email', type: 'string', unique: true),
        ]));

        self::assertSame([], $errors);
    }

    public function testRejectsMissingPrimaryKey(): void
    {
        $errors = (new Validator())->collectErrors($this->spec([
            new PersistenceFieldSpec(name: 'email', type: 'string'),
        ]));

        self::assertContains(
            'persistence.entity.fields must declare exactly one primary field (found 0).',
            $errors,
        );
    }

    public function testRejectsMultiplePrimaryKeys(): void
    {
        $errors = (new Validator())->collectErrors($this->spec([
            new PersistenceFieldSpec(name: 'id1', type: 'uuid', primary: true),
            new PersistenceFieldSpec(name: 'id2', type: 'uuid', primary: true),
        ]));

        self::assertContains(
            'persistence.entity.fields must declare exactly one primary field (found 2).',
            $errors,
        );
    }

    public function testRejectsUnknownColumnType(): void
    {
        $errors = (new Validator())->collectErrors($this->spec([
            new PersistenceFieldSpec(name: 'id', type: 'uuid', primary: true),
            new PersistenceFieldSpec(name: 'weight', type: 'unobtanium'),
        ]));

        self::assertContains(
            "persistence field 'weight' has unknown type 'unobtanium'.",
            $errors,
        );
    }

    public function testRejectsEmptyTableName(): void
    {
        $spec = new Spec(
            endpoint: new EndpointSpec('POST', '/users', '', []),
            inputs: [],
            outputs: [],
            domain: new DomainSpec('App\\User\\CreateUser'),
            persistence: new PersistenceSpec(
                entity: new PersistenceEntitySpec(
                    class: 'App\\User\\User',
                    table: '',
                    fields: [new PersistenceFieldSpec(name: 'id', type: 'uuid', primary: true)],
                ),
                repository: 'App\\User\\UserRepository',
            ),
        );

        $errors = (new Validator())->collectErrors($spec);

        self::assertContains('persistence.entity.table must not be empty.', $errors);
    }

    /**
     * @param list<PersistenceFieldSpec> $fields
     */
    private function spec(array $fields): Spec
    {
        return new Spec(
            endpoint: new EndpointSpec('POST', '/users', '', []),
            inputs: [],
            outputs: [],
            domain: new DomainSpec('App\\User\\CreateUser'),
            persistence: new PersistenceSpec(
                entity: new PersistenceEntitySpec(
                    class: 'App\\User\\User',
                    table: 'users',
                    fields: $fields,
                ),
                repository: 'App\\User\\UserRepository',
            ),
        );
    }
}

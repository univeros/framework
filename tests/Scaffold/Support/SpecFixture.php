<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Support;

use Altair\Scaffold\Spec\Ast\DomainSpec;
use Altair\Scaffold\Spec\Ast\EndpointSpec;
use Altair\Scaffold\Spec\Ast\InputFieldSpec;
use Altair\Scaffold\Spec\Ast\OutputResponseSpec;
use Altair\Scaffold\Spec\Ast\Spec;

final class SpecFixture
{
    public static function createUser(): Spec
    {
        return new Spec(
            endpoint: new EndpointSpec(
                method: 'POST',
                path: '/users',
                summary: 'Create a new user',
                tags: ['users'],
            ),
            inputs: [
                new InputFieldSpec(name: 'email', type: 'string', rules: ['email', 'required']),
                new InputFieldSpec(name: 'password', type: 'string', rules: ['min:8', 'required'], sensitive: true),
            ],
            outputs: [
                new OutputResponseSpec(status: 201, body: ['user' => 'App\\User\\User']),
                new OutputResponseSpec(status: 422, body: ['errors' => 'array<string, list<string>>']),
                new OutputResponseSpec(status: 409, body: ['message' => 'string']),
            ],
            domain: new DomainSpec(class: 'App\\User\\CreateUser'),
            sourcePath: 'api/users/create.yaml',
        );
    }
}

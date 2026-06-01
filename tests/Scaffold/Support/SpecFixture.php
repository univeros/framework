<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Support;

use Altair\Scaffold\Spec\Ast\DomainSpec;
use Altair\Scaffold\Spec\Ast\EndpointSpec;
use Altair\Scaffold\Spec\Ast\IdempotencySpec;
use Altair\Scaffold\Spec\Ast\InputFieldSpec;
use Altair\Scaffold\Spec\Ast\OutputResponseSpec;
use Altair\Scaffold\Spec\Ast\PersistenceEntitySpec;
use Altair\Scaffold\Spec\Ast\PersistenceFieldSpec;
use Altair\Scaffold\Spec\Ast\PersistenceSpec;
use Altair\Scaffold\Spec\Ast\QueueDispatchSpec;
use Altair\Scaffold\Spec\Ast\Spec;
use Altair\Scaffold\Spec\Ast\WebhookSpec;

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

    public static function createUserWithQueue(): Spec
    {
        $base = self::createUser();

        return new Spec(
            endpoint: $base->endpoint,
            inputs: $base->inputs,
            outputs: $base->outputs,
            domain: $base->domain,
            sourcePath: $base->sourcePath,
            queue: [
                new QueueDispatchSpec(
                    name: 'on_create',
                    message: 'App\\Messages\\SendWelcomeEmail',
                    fields: ['userId' => 'string', 'email' => 'string'],
                    transport: 'default',
                ),
            ],
        );
    }

    public static function createUserWithIdempotency(): Spec
    {
        $base = self::createUser();

        return new Spec(
            endpoint: $base->endpoint,
            inputs: $base->inputs,
            outputs: $base->outputs,
            domain: $base->domain,
            sourcePath: $base->sourcePath,
            idempotency: new IdempotencySpec(
                ttl: '24h',
                scope: 'tenant',
                mode: IdempotencySpec::MODE_REQUIRED,
            ),
        );
    }

    public static function createUserWithInboundWebhook(): Spec
    {
        $base = self::createUser();

        return new Spec(
            endpoint: $base->endpoint,
            inputs: $base->inputs,
            outputs: $base->outputs,
            domain: $base->domain,
            sourcePath: $base->sourcePath,
            webhook: new WebhookSpec(
                direction: WebhookSpec::DIRECTION_IN,
                signing: 'hmac-sha256',
                secretName: 'stripe',
                signatureHeader: 'Stripe-Signature',
                dedupeTtl: '24h',
            ),
        );
    }

    public static function createUserWithOutboundWebhook(): Spec
    {
        $base = self::createUser();

        return new Spec(
            endpoint: $base->endpoint,
            inputs: $base->inputs,
            outputs: $base->outputs,
            domain: $base->domain,
            sourcePath: $base->sourcePath,
            webhook: new WebhookSpec(
                direction: WebhookSpec::DIRECTION_OUT,
                signing: 'ed25519',
                retryMaxAttempts: 8,
                retryBackoff: WebhookSpec::BACKOFF_LINEAR,
                deadLetterTransport: 'webhook.deadletter',
            ),
        );
    }

    public static function createUserWithPersistence(): Spec
    {
        $base = self::createUser();

        return new Spec(
            endpoint: $base->endpoint,
            inputs: $base->inputs,
            outputs: $base->outputs,
            domain: $base->domain,
            sourcePath: $base->sourcePath,
            persistence: new PersistenceSpec(
                entity: new PersistenceEntitySpec(
                    class: 'App\\User\\User',
                    table: 'users',
                    fields: [
                        new PersistenceFieldSpec(name: 'id', type: 'uuid', primary: true),
                        new PersistenceFieldSpec(name: 'email', type: 'string', unique: true),
                        new PersistenceFieldSpec(name: 'password_hash', type: 'string'),
                        new PersistenceFieldSpec(
                            name: 'created_at',
                            type: 'datetime',
                            hasDefault: true,
                            default: 'now',
                        ),
                    ],
                ),
                repository: 'App\\User\\UserRepository',
            ),
        );
    }
}

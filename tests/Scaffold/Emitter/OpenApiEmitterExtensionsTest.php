<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Emitter;

use Altair\Scaffold\Emitter\OpenApiEmitter;
use Altair\Tests\Scaffold\Support\SpecFixture;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class OpenApiEmitterExtensionsTest extends TestCase
{
    public function testEmitsXAltairDomainBlock(): void
    {
        $file = (new OpenApiEmitter())->emit(SpecFixture::createUser());
        $doc = Yaml::parse($file->contents);

        $operation = $doc['paths']['/users']['post'];
        self::assertArrayHasKey('x-altair-domain', $operation);
        self::assertSame('App\\User\\CreateUser', $operation['x-altair-domain']['class']);
        self::assertSame('__invoke', $operation['x-altair-domain']['invocation']);
    }

    public function testEmitsXAltairPersistenceBlock(): void
    {
        $file = (new OpenApiEmitter())->emit(SpecFixture::createUserWithPersistence());
        $doc = Yaml::parse($file->contents);

        $operation = $doc['paths']['/users']['post'];
        self::assertArrayHasKey('x-altair-persistence', $operation);

        $persistence = $operation['x-altair-persistence'];
        self::assertSame('App\\User\\User', $persistence['entity']['class']);
        self::assertSame('users', $persistence['entity']['table']);
        self::assertSame('App\\User\\UserRepository', $persistence['repository']);

        $fields = $persistence['entity']['fields'];
        self::assertSame('uuid', $fields['id']['type']);
        self::assertTrue($fields['id']['primary']);
        self::assertSame('string', $fields['email']['type']);
        self::assertTrue($fields['email']['unique']);
        self::assertSame('datetime', $fields['created_at']['type']);
        self::assertSame('now', $fields['created_at']['default']);
    }

    public function testEmitsXAltairQueueBlock(): void
    {
        $file = (new OpenApiEmitter())->emit(SpecFixture::createUserWithQueue());
        $doc = Yaml::parse($file->contents);

        $operation = $doc['paths']['/users']['post'];
        self::assertArrayHasKey('x-altair-queue', $operation);

        $queue = $operation['x-altair-queue'];
        self::assertCount(1, $queue);
        self::assertSame('on_create', $queue[0]['name']);
        self::assertSame('App\\Messages\\SendWelcomeEmail', $queue[0]['message']);
        self::assertSame('default', $queue[0]['transport']);
        self::assertSame(['userId' => 'string', 'email' => 'string'], $queue[0]['fields']);
    }

    public function testOmitsBlocksWhenSpecHasNeither(): void
    {
        $file = (new OpenApiEmitter())->emit(SpecFixture::createUser());
        $doc = Yaml::parse($file->contents);

        $operation = $doc['paths']['/users']['post'];
        self::assertArrayNotHasKey('x-altair-persistence', $operation);
        self::assertArrayNotHasKey('x-altair-queue', $operation);
    }
}

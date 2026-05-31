<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Emitter;

use Altair\Scaffold\Emitter\ActionEmitter;
use Altair\Tests\Scaffold\Support\SpecFixture;
use PHPUnit\Framework\TestCase;

final class ActionEmitterIdempotencyTest extends TestCase
{
    public function testGeneratedActionWithoutIdempotencyHasNoAccessor(): void
    {
        $file = (new ActionEmitter())->emit(SpecFixture::createUser());

        self::assertStringNotContainsString('idempotency()', $file->contents);
    }

    public function testGeneratedActionExposesStaticIdempotencyAccessor(): void
    {
        $file = (new ActionEmitter())->emit(SpecFixture::createUserWithIdempotency());

        self::assertStringContainsString('public static function idempotency()', $file->contents);
        self::assertStringContainsString("'ttl' => '24h'", $file->contents);
        self::assertStringContainsString("'scope' => 'tenant'", $file->contents);
        self::assertStringContainsString("'mode' => 'required'", $file->contents);
    }

    public function testGeneratedActionWithIdempotencyIsSyntacticallyValid(): void
    {
        $file = (new ActionEmitter())->emit(SpecFixture::createUserWithIdempotency());

        // Tokenise to confirm the generated PHP parses cleanly.
        $tokens = @\token_get_all($file->contents, TOKEN_PARSE);
        self::assertIsArray($tokens);
    }
}

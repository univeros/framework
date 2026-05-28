<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Guard;

use Altair\Mcp\Guard\ServerMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServerMode::class)]
final class ServerModeTest extends TestCase
{
    public function testDefaultAllowsFileMutationButNotDbWrites(): void
    {
        $mode = new ServerMode();

        self::assertTrue($mode->allowsFileMutation());
        self::assertFalse($mode->allowsDbWrites());
    }

    public function testReadonlyBlocksEverything(): void
    {
        $mode = new ServerMode(readonly: true, allowWrites: true);

        self::assertFalse($mode->allowsFileMutation());
        self::assertFalse($mode->allowsDbWrites());
    }

    public function testAllowWritesEnablesDbWrites(): void
    {
        $mode = new ServerMode(allowWrites: true);

        self::assertTrue($mode->allowsDbWrites());
    }
}

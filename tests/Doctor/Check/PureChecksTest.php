<?php

declare(strict_types=1);

namespace Altair\Tests\Doctor\Check;

use Altair\Doctor\Check\ExtensionsLoadedCheck;
use Altair\Doctor\Check\PhpVersionCheck;
use Altair\Doctor\Result\CheckStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhpVersionCheck::class)]
#[CoversClass(ExtensionsLoadedCheck::class)]
class PureChecksTest extends TestCase
{
    public function testPhpVersionOkWhenCurrentSatisfiesFloor(): void
    {
        $result = (new PhpVersionCheck('8.3', '8.4.1'))->run();

        $this->assertSame(CheckStatus::Ok, $result->status);
        $this->assertSame('php_version', $result->name);
    }

    public function testPhpVersionErrorsWhenBelowFloorWithInstallAction(): void
    {
        $result = (new PhpVersionCheck('8.3', '8.1.0'))->run();

        $this->assertSame(CheckStatus::Error, $result->status);
        $this->assertNotNull($result->agentAction);
        $this->assertSame('install_dep', $result->agentAction->type);
    }

    public function testExtensionsOkWhenAllLoaded(): void
    {
        $check = new ExtensionsLoadedCheck(['redis', 'pdo'], static fn(string $ext): bool => true);

        $this->assertSame(CheckStatus::Ok, $check->run()->status);
    }

    public function testExtensionsErrorListsMissingAndSuggestsInstall(): void
    {
        $check = new ExtensionsLoadedCheck(
            ['redis', 'pdo'],
            static fn(string $ext): bool => $ext === 'pdo',
        );

        $result = $check->run();
        $this->assertSame(CheckStatus::Error, $result->status);
        $this->assertStringContainsString('redis', $result->detail);
        $this->assertStringNotContainsString('pdo', $result->detail);
        $this->assertNotNull($result->agentAction);
        $this->assertSame('ext-redis', $result->agentAction->toArray()['package']);
    }

    public function testExtensionsOkWhenNoneRequired(): void
    {
        $this->assertSame(CheckStatus::Ok, (new ExtensionsLoadedCheck([]))->run()->status);
    }
}

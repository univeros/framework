<?php

declare(strict_types=1);

namespace Altair\Tests\Doctor\Check;

use Altair\Container\Container;
use Altair\Doctor\Check\ContainerBootsCheck;
use Altair\Doctor\Check\ContainerResolvesCheck;
use Altair\Doctor\Check\DatabaseReachableCheck;
use Altair\Doctor\Result\CheckStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ContainerBootsCheck::class)]
#[CoversClass(ContainerResolvesCheck::class)]
#[CoversClass(DatabaseReachableCheck::class)]
class HostAppChecksTest extends TestCase
{
    public function testContainerBootsSkippedWithoutBooter(): void
    {
        $this->assertSame(CheckStatus::Skipped, (new ContainerBootsCheck())->run()->status);
    }

    public function testContainerBootsOkWhenBooterReturnsCleanly(): void
    {
        $check = new ContainerBootsCheck(static fn(): \stdClass => new \stdClass());

        $this->assertSame(CheckStatus::Ok, $check->run()->status);
    }

    public function testContainerBootsErrorsWhenBooterThrows(): void
    {
        $check = new ContainerBootsCheck(static function (): void {
            throw new RuntimeException('missing binding for FooInterface');
        });

        $result = $check->run();
        $this->assertSame(CheckStatus::Error, $result->status);
        $this->assertStringContainsString('missing binding for FooInterface', $result->detail);
    }

    public function testContainerResolvesSkippedWithEmptyList(): void
    {
        $this->assertSame(CheckStatus::Skipped, (new ContainerResolvesCheck(new Container(), []))->run()->status);
    }

    public function testContainerResolvesOkWhenAllBindingsResolve(): void
    {
        $container = new Container();
        $container->share(new \stdClass());

        $this->assertSame(CheckStatus::Ok, (new ContainerResolvesCheck($container, [\stdClass::class]))->run()->status);
    }

    public function testContainerResolvesErrorListsFailedIds(): void
    {
        $container = new Container();
        $container->share(new \stdClass());

        $result = (new ContainerResolvesCheck($container, [\stdClass::class, 'App\\Missing\\Service']))->run();
        $this->assertSame(CheckStatus::Error, $result->status);
        $this->assertStringContainsString('App\\Missing\\Service', $result->detail);
        $this->assertStringNotContainsString('stdClass (', $result->detail, 'a resolving id must not appear in the failure list');
    }

    public function testContainerResolvesDependsOnContainerBoots(): void
    {
        $this->assertSame(['container_boots'], (new ContainerResolvesCheck(new Container(), []))->dependsOn());
    }

    public function testDatabaseReachableSkippedWithoutProbe(): void
    {
        $this->assertSame(CheckStatus::Skipped, (new DatabaseReachableCheck())->run()->status);
    }

    public function testDatabaseReachableOkWhenProbeReturnsTrue(): void
    {
        $this->assertSame(CheckStatus::Ok, (new DatabaseReachableCheck(static fn(): bool => true))->run()->status);
    }

    public function testDatabaseReachableErrorsWhenProbeReturnsFalse(): void
    {
        $this->assertSame(CheckStatus::Error, (new DatabaseReachableCheck(static fn(): bool => false))->run()->status);
    }

    public function testDatabaseReachableErrorsWhenProbeThrows(): void
    {
        $check = new DatabaseReachableCheck(static function (): bool {
            throw new RuntimeException('connection refused');
        });

        $result = $check->run();
        $this->assertSame(CheckStatus::Error, $result->status);
        $this->assertStringContainsString('connection refused', $result->detail);
    }
}

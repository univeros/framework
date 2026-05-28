<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Observatory\Panel;

use Altair\Container\Container;
use Altair\Introspection\Inspector\ContainerInspector;
use Altair\Observatory\Panel\ContainerPanel;
use Altair\Observatory\Panel\PanelStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

#[CoversClass(ContainerPanel::class)]
final class ContainerPanelTest extends TestCase
{
    public function testIdentity(): void
    {
        $panel = new ContainerPanel(new ContainerInspector(new Container()));

        self::assertSame('container', $panel->id());
        self::assertSame('Container', $panel->label());
        self::assertSame('cube', $panel->icon());
    }

    public function testSnapshotReportsBindings(): void
    {
        $container = new Container();
        $container->alias(LoggerInterface::class, NullLogger::class);
        $container->share(NullLogger::class);

        $snapshot = (new ContainerPanel(new ContainerInspector($container)))->snapshot();

        self::assertSame(PanelStatus::Ok, $snapshot->status);
        self::assertGreaterThan(0, $snapshot->metrics['bindings']);
        self::assertStringContainsString('binding', $snapshot->headline);
        self::assertNotSame([], $snapshot->items);

        $first = $snapshot->items[0];
        self::assertArrayHasKey('id', $first);
        self::assertArrayHasKey('kind', $first);
        self::assertArrayHasKey('shared', $first);
    }

    public function testEmptyContainerDegradesToUnknown(): void
    {
        $snapshot = (new ContainerPanel(new ContainerInspector(new Container())))->snapshot();

        self::assertSame(PanelStatus::Unknown, $snapshot->status);
        self::assertStringContainsString('unavailable', $snapshot->headline);
        self::assertSame([], $snapshot->items);
        self::assertSame(0, $snapshot->metrics['bindings']);
    }
}

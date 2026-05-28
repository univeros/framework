<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Observatory;

use Altair\Observatory\Panel\PanelStatus;
use Altair\Observatory\Panel\RuntimePanel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RuntimePanel::class)]
final class RuntimePanelTest extends TestCase
{
    public function testIdentity(): void
    {
        $panel = new RuntimePanel();

        self::assertSame('runtime', $panel->id());
        self::assertSame('Runtime', $panel->label());
        self::assertSame('server', $panel->icon());
    }

    public function testSnapshotReportsRuntime(): void
    {
        $snapshot = (new RuntimePanel())->snapshot();

        self::assertSame(PanelStatus::Ok, $snapshot->status);
        self::assertStringStartsWith('PHP ', $snapshot->headline);
        self::assertSame(PHP_VERSION, $snapshot->metrics['php_version']);
        self::assertGreaterThan(0, $snapshot->metrics['extensions']);
        self::assertNotSame([], $snapshot->items);
        self::assertArrayHasKey('extension', $snapshot->items[0]);
    }
}

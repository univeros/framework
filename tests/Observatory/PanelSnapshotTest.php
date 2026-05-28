<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Observatory;

use Altair\Observatory\Panel\PanelSnapshot;
use Altair\Observatory\Panel\PanelStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PanelSnapshot::class)]
#[CoversClass(PanelStatus::class)]
final class PanelSnapshotTest extends TestCase
{
    public function testToArrayProjectsAllFields(): void
    {
        $snapshot = new PanelSnapshot(
            PanelStatus::Warning,
            '3 failures',
            ['total' => 12, 'failed' => 3],
            [['id' => 'a'], ['id' => 'b']],
        );

        self::assertSame([
            'status' => 'warning',
            'headline' => '3 failures',
            'metrics' => ['total' => 12, 'failed' => 3],
            'items' => [['id' => 'a'], ['id' => 'b']],
        ], $snapshot->toArray());
    }

    public function testDefaultsToEmptyMetricsAndItems(): void
    {
        $snapshot = new PanelSnapshot(PanelStatus::Ok, 'All good');

        self::assertSame('ok', $snapshot->toArray()['status']);
        self::assertSame([], $snapshot->metrics);
        self::assertSame([], $snapshot->items);
    }
}

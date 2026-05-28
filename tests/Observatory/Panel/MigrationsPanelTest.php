<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Observatory\Panel;

use Altair\Observatory\Contracts\MigrationStatusReaderInterface;
use Altair\Observatory\Panel\MigrationsPanel;
use Altair\Observatory\Panel\MigrationStatus;
use Altair\Observatory\Panel\PanelStatus;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MigrationsPanel::class)]
#[CoversClass(MigrationStatus::class)]
final class MigrationsPanelTest extends TestCase
{
    public function testIdentity(): void
    {
        $panel = new MigrationsPanel($this->reader(null));

        self::assertSame('migrations', $panel->id());
        self::assertSame('Migrations', $panel->label());
        self::assertSame('circle-stack', $panel->icon());
    }

    public function testOkWhenNothingPending(): void
    {
        $panel = new MigrationsPanel($this->reader([
            new MigrationStatus('20260101_create_users', true),
            new MigrationStatus('20260102_create_orders', true),
        ]));

        $snapshot = $panel->snapshot();

        self::assertSame(PanelStatus::Ok, $snapshot->status);
        self::assertSame('0 pending', $snapshot->headline);
        self::assertSame(2, $snapshot->metrics['applied']);
        self::assertSame(0, $snapshot->metrics['pending']);
        // No pending rows, so the recent applied migrations fill the list.
        self::assertCount(2, $snapshot->items);
        self::assertSame('20260101_create_users', $snapshot->items[0]['name']);
        self::assertTrue($snapshot->items[0]['applied']);
    }

    public function testWarningWhenPending(): void
    {
        $panel = new MigrationsPanel($this->reader([
            new MigrationStatus('20260101_create_users', true),
            new MigrationStatus('20260102_create_orders', false),
            new MigrationStatus('20260103_add_index', false),
        ]));

        $snapshot = $panel->snapshot();

        self::assertSame(PanelStatus::Warning, $snapshot->status);
        self::assertSame('2 pending', $snapshot->headline);
        self::assertSame(1, $snapshot->metrics['applied']);
        self::assertSame(2, $snapshot->metrics['pending']);
        // Only the pending migrations are listed, in order.
        self::assertCount(2, $snapshot->items);
        self::assertSame('20260102_create_orders', $snapshot->items[0]['name']);
        self::assertFalse($snapshot->items[0]['applied']);
        self::assertSame('20260103_add_index', $snapshot->items[1]['name']);
    }

    public function testUnknownWhenUnreadable(): void
    {
        $panel = new MigrationsPanel($this->reader(null));

        $snapshot = $panel->snapshot();

        self::assertSame(PanelStatus::Unknown, $snapshot->status);
        self::assertSame('unavailable', $snapshot->headline);
        self::assertNull($snapshot->metrics['applied']);
        self::assertNull($snapshot->metrics['pending']);
        self::assertSame([], $snapshot->items);
    }

    /**
     * @param list<MigrationStatus>|null $migrations
     */
    private function reader(?array $migrations): MigrationStatusReaderInterface
    {
        return new readonly class ($migrations) implements MigrationStatusReaderInterface {
            /**
             * @param list<MigrationStatus>|null $migrations
             */
            public function __construct(private ?array $migrations) {}

            #[Override]
            public function read(): ?array
            {
                return $this->migrations;
            }
        };
    }
}

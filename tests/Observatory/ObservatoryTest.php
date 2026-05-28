<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Observatory;

use Altair\Observatory\Observatory;
use Altair\Observatory\Panel\RuntimePanel;
use Altair\Observatory\PanelRegistry;
use Altair\Observatory\Security\EnvironmentAccessGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Observatory::class)]
final class ObservatoryTest extends TestCase
{
    public function testIsAccessibleDelegatesToGuard(): void
    {
        self::assertTrue($this->observatory(true)->isAccessible());
        self::assertFalse($this->observatory(false)->isAccessible());
    }

    public function testDashboardProjectsEveryPanel(): void
    {
        $dashboard = $this->observatory(true)->dashboard();

        self::assertArrayHasKey('runtime', $dashboard);
        self::assertSame('Runtime', $dashboard['runtime']['label']);
        self::assertSame('server', $dashboard['runtime']['icon']);
        self::assertSame('ok', $dashboard['runtime']['snapshot']['status']);
    }

    public function testPanelsExposesRegisteredPanels(): void
    {
        $panels = $this->observatory(true)->panels();

        self::assertCount(1, $panels);
        self::assertSame('runtime', $panels[0]->id());
    }

    private function observatory(bool $enabled): Observatory
    {
        return new Observatory(
            new PanelRegistry([new RuntimePanel()]),
            new EnvironmentAccessGuard($enabled, 'local'),
        );
    }
}

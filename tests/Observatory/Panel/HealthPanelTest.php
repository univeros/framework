<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Observatory\Panel;

use Altair\Doctor\CheckRegistry;
use Altair\Doctor\Doctor;
use Altair\Doctor\Result\CheckResult;
use Altair\Observatory\Panel\HealthPanel;
use Altair\Observatory\Panel\PanelStatus;
use Altair\Tests\Doctor\Support\FakeCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HealthPanel::class)]
final class HealthPanelTest extends TestCase
{
    public function testIdentity(): void
    {
        $panel = new HealthPanel(new Doctor(new CheckRegistry()));

        self::assertSame('health', $panel->id());
        self::assertSame('Health', $panel->label());
        self::assertSame('heart-pulse', $panel->icon());
    }

    public function testFailingCheckMakesPanelCritical(): void
    {
        $panel = new HealthPanel($this->doctor([
            new FakeCheck('a', CheckResult::ok('a', 'all good')),
            new FakeCheck('b', CheckResult::ok('b', 'all good')),
            new FakeCheck('c', CheckResult::error('c', 'boom')),
        ]));

        $snapshot = $panel->snapshot();

        self::assertSame(PanelStatus::Critical, $snapshot->status);
        self::assertSame('2/3 passing', $snapshot->headline);
        self::assertSame(3, $snapshot->metrics['total']);
        self::assertSame(2, $snapshot->metrics['passing']);
        self::assertSame(1, $snapshot->metrics['failed']);
        self::assertSame(0, $snapshot->metrics['skipped']);
    }

    public function testWarnCheckCountsAsFailedAndCritical(): void
    {
        $panel = new HealthPanel($this->doctor([
            new FakeCheck('a', CheckResult::ok('a', 'ok')),
            new FakeCheck('b', CheckResult::warn('b', 'careful')),
        ]));

        $snapshot = $panel->snapshot();

        self::assertSame(PanelStatus::Critical, $snapshot->status);
        self::assertSame('1/2 passing', $snapshot->headline);
        self::assertSame(1, $snapshot->metrics['failed']);
    }

    public function testAllOkIsOk(): void
    {
        $panel = new HealthPanel($this->doctor([
            new FakeCheck('a', CheckResult::ok('a', 'ok')),
            new FakeCheck('b', CheckResult::ok('b', 'ok')),
        ]));

        $snapshot = $panel->snapshot();

        self::assertSame(PanelStatus::Ok, $snapshot->status);
        self::assertSame('2/2 passing', $snapshot->headline);
    }

    public function testSkippedOnlyRunIsWarning(): void
    {
        $panel = new HealthPanel($this->doctor([
            new FakeCheck('a', CheckResult::ok('a', 'ok')),
            new FakeCheck('b', CheckResult::skipped('b', 'not applicable')),
        ]));

        $snapshot = $panel->snapshot();

        self::assertSame(PanelStatus::Warning, $snapshot->status);
        self::assertSame('1/2 passing', $snapshot->headline);
        self::assertSame(1, $snapshot->metrics['skipped']);
    }

    public function testEmptyRunIsUnknown(): void
    {
        $panel = new HealthPanel($this->doctor([]));

        $snapshot = $panel->snapshot();

        self::assertSame(PanelStatus::Unknown, $snapshot->status);
        self::assertSame('0/0 passing', $snapshot->headline);
        self::assertSame([], $snapshot->items);
    }

    public function testItemsAreOneRowPerCheckWithStatusAndSummary(): void
    {
        $panel = new HealthPanel($this->doctor([
            new FakeCheck('php_version', CheckResult::ok('php_version', 'PHP 8.3 detected')),
            new FakeCheck('composer_deps', CheckResult::error('composer_deps', 'lockfile stale')),
        ]));

        $snapshot = $panel->snapshot();

        self::assertSame([
            ['name' => 'php_version', 'status' => 'ok', 'summary' => 'PHP 8.3 detected'],
            ['name' => 'composer_deps', 'status' => 'error', 'summary' => 'lockfile stale'],
        ], $snapshot->items);
    }

    public function testOnlyFilterRunsTheNamedSubset(): void
    {
        $cheap = new FakeCheck('cheap', CheckResult::ok('cheap', 'fast'));
        $slow = new FakeCheck('phpstan', CheckResult::ok('phpstan', 'slow'));
        $panel = new HealthPanel($this->doctor([$cheap, $slow]), ['cheap']);

        $snapshot = $panel->snapshot();

        self::assertTrue($cheap->ran);
        self::assertFalse($slow->ran, 'the slow check must be excluded by the only-filter');
        self::assertSame('1/1 passing', $snapshot->headline);
    }

    /**
     * @param list<FakeCheck> $checks
     */
    private function doctor(array $checks): Doctor
    {
        return new Doctor(new CheckRegistry($checks));
    }
}

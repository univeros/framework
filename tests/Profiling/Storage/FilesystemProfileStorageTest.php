<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Profiling\Storage;

use Altair\Profiling\Model\CallNode;
use Altair\Profiling\Model\Hotspot;
use Altair\Profiling\Model\ProfileReport;
use Altair\Profiling\Storage\FilesystemProfileStorage;
use Altair\Profiling\Storage\ProfileSummary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FilesystemProfileStorage::class)]
#[CoversClass(ProfileSummary::class)]
#[CoversClass(ProfileReport::class)]
final class FilesystemProfileStorageTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/altair-profiling-' . bin2hex(random_bytes(5));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->dir)) {
            return;
        }

        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->dir);
    }

    public function testSavedReportRoundTripsThroughLoad(): void
    {
        $storage = new FilesystemProfileStorage($this->dir);
        $report = $this->makeReport('abc');

        $storage->save($report);
        $loaded = $storage->load('abc');

        self::assertNotNull($loaded);
        self::assertSame('abc', $loaded->id);
        self::assertSame('demo', $loaded->target);
        self::assertSame(7, $loaded->totalSamples);
    }

    public function testListReturnsLightweightSummariesNewestFirst(): void
    {
        $storage = new FilesystemProfileStorage($this->dir);
        $storage->save($this->makeReport('one'));
        touch($this->dir . '/one.json', time() - 100);
        $storage->save($this->makeReport('two'));

        $summaries = $storage->list();

        self::assertCount(2, $summaries);
        self::assertContainsOnlyInstancesOf(ProfileSummary::class, $summaries);
        self::assertSame('two', $summaries[0]->id);
    }

    public function testRotationDropsOldestPastMaxKept(): void
    {
        $storage = new FilesystemProfileStorage($this->dir, maxKept: 2);

        $storage->save($this->makeReport('a'));
        touch($this->dir . '/a.json', time() - 200);
        $storage->save($this->makeReport('b'));
        touch($this->dir . '/b.json', time() - 100);
        $storage->save($this->makeReport('c'));

        self::assertNull($storage->load('a'), 'oldest should be rotated out');
        self::assertNotNull($storage->load('b'));
        self::assertNotNull($storage->load('c'));
    }

    public function testLoadMissingIdReturnsNull(): void
    {
        self::assertNull((new FilesystemProfileStorage($this->dir))->load('nope'));
    }

    public function testDeleteRemovesAStoredProfile(): void
    {
        $storage = new FilesystemProfileStorage($this->dir);
        $storage->save($this->makeReport('zap'));

        $storage->delete('zap');

        self::assertNull($storage->load('zap'));
    }

    private function makeReport(string $id): ProfileReport
    {
        return new ProfileReport(
            $id,
            'demo',
            '2026-05-29T12:00:00Z',
            totalSamples: 7,
            durationMs: 42,
            periodUs: 1_000,
            backend: 'excimer',
            tree: new CallNode('<root>', 0, 7, [
                new CallNode('App\Slow', 7, 7, []),
            ]),
            hotspots: [new Hotspot('App\Slow', 7, 7, 100.0)],
        );
    }
}

<?php

declare(strict_types=1);

namespace Altair\Tests\Introspection\Inspector;

use Altair\Introspection\Inspector\ManifestDiffInspector;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ManifestDiffInspector::class)]
class ManifestDiffInspectorTest extends TestCase
{
    private string $tmpRoot;

    #[Override]
    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/altair-manifest-diff-' . bin2hex(random_bytes(4));
        @mkdir($this->tmpRoot . '/packages', 0775, true);
        file_put_contents($this->tmpRoot . '/MANIFEST.md', "Index\n");
        file_put_contents($this->tmpRoot . '/packages/foo.md', "old content\n");
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach (glob($this->tmpRoot . '/packages/*') ?: [] as $f) {
            @unlink($f);
        }

        @rmdir($this->tmpRoot . '/packages');
        @unlink($this->tmpRoot . '/MANIFEST.md');
        @rmdir($this->tmpRoot);
    }

    public function testDiffReportsInSyncWhenContentMatches(): void
    {
        $table = (new ManifestDiffInspector($this->tmpRoot))->diff([
            'MANIFEST.md' => "Index\n",
            'packages/foo.md' => "old content\n",
        ]);

        $this->assertSame([], $table->rows);
        $this->assertTrue($table->extras['in_sync']);
    }

    public function testDiffReportsStaleMissingExtra(): void
    {
        $table = (new ManifestDiffInspector($this->tmpRoot))->diff([
            'MANIFEST.md' => "DIFFERENT\n",
            'packages/bar.md' => "new\n", // missing on disk
            // packages/foo.md present on disk but absent here → extra
        ]);

        $statuses = array_column($table->rows, 'status');
        sort($statuses);
        $this->assertSame(['extra', 'missing', 'stale'], $statuses);
        $this->assertFalse($table->extras['in_sync']);
        $this->assertSame(1, $table->extras['stale']);
        $this->assertSame(1, $table->extras['missing']);
        $this->assertSame(1, $table->extras['extra']);
    }
}

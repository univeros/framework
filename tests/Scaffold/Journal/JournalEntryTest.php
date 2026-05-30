<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Journal;

use Altair\Scaffold\Journal\Exception\JournalException;
use Altair\Scaffold\Journal\FileSnapshot;
use Altair\Scaffold\Journal\JournalEntry;
use Altair\Scaffold\Journal\OperationKind;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JournalEntry::class)]
#[CoversClass(FileSnapshot::class)]
#[CoversClass(OperationKind::class)]
class JournalEntryTest extends TestCase
{
    public function testOpenApiImportFactoryEmbedsSourceDocument(): void
    {
        $entry = JournalEntry::openApiImport(
            command: 'bin/altair openapi:import openapi.yaml --scaffold',
            documentPath: 'openapi.yaml',
            documentContent: "openapi: 3.1.0\ninfo: { title: X, version: 1.0 }\npaths: {}\n",
            scaffoldVersion: '1.0',
            filesCreated: [FileSnapshot::created('api/users/create.yaml', str_repeat('a', 64), 200)],
            timestamp: new DateTimeImmutable('2026-05-30T12:00:00Z'),
            user: 'tester',
        );

        $expectedSha = hash('sha256', "openapi: 3.1.0\ninfo: { title: X, version: 1.0 }\npaths: {}\n");
        $this->assertSame(OperationKind::Scaffold, $entry->operation);
        $this->assertSame('20260530T120000Z-' . substr($expectedSha, 0, 8), $entry->id);
        $this->assertSame('openapi.yaml', $entry->spec['path']);
        $this->assertSame($expectedSha, $entry->spec['sha256']);
        $this->assertStringContainsString('openapi: 3.1.0', $entry->spec['content_inline']);
        $this->assertCount(1, $entry->filesCreated);
        $this->assertSame('tester', $entry->user);
    }

    public function testScaffoldFactoryStampsIdAndShaFromContent(): void
    {
        $entry = JournalEntry::scaffold(
            command: 'bin/altair spec:scaffold api/users/create.yaml',
            specPath: 'api/users/create.yaml',
            specContent: "endpoint:\n  method: POST\n",
            scaffoldVersion: '1.0',
            filesCreated: [FileSnapshot::created('src/Foo.php', str_repeat('a', 64), 100)],
            timestamp: new DateTimeImmutable('2026-05-27T10:00:00Z'),
            user: 'tester',
        );

        $this->assertSame(OperationKind::Scaffold, $entry->operation);
        $this->assertSame('20260527T100000Z-' . substr(hash('sha256', "endpoint:\n  method: POST\n"), 0, 8), $entry->id);
        $this->assertSame('tester', $entry->user);
        $this->assertCount(1, $entry->filesCreated);
        $this->assertSame('api/users/create.yaml', $entry->spec['path']);
        $this->assertSame(hash('sha256', "endpoint:\n  method: POST\n"), $entry->spec['sha256']);
    }

    public function testRoundTripsThroughJson(): void
    {
        $entry = JournalEntry::scaffold(
            command: 'bin/altair spec:scaffold api/users/create.yaml',
            specPath: 'api/users/create.yaml',
            specContent: "endpoint:\n  method: POST\n",
            scaffoldVersion: '1.0',
            filesCreated: [FileSnapshot::created('src/Foo.php', hash('sha256', 'foo'), 3)],
            filesModified: [FileSnapshot::modified('config/routes.php', hash('sha256', 'a'), hash('sha256', 'b'), '@@ diff @@', 'a')],
            filesSkipped: ['src/Bar.php'],
            openapiFragmentPath: 'docs/openapi/users-create.yaml',
            timestamp: new DateTimeImmutable('2026-05-27T10:00:00Z'),
        );

        $rebuilt = JournalEntry::fromArray(json_decode($entry->toJson(), true, 512, JSON_THROW_ON_ERROR));

        $this->assertSame($entry->id, $rebuilt->id);
        $this->assertSame($entry->command, $rebuilt->command);
        $this->assertSame($entry->spec, $rebuilt->spec);
        $this->assertCount(1, $rebuilt->filesCreated);
        $this->assertCount(1, $rebuilt->filesModified);
        $this->assertSame(['src/Bar.php'], $rebuilt->filesSkipped);
        $this->assertSame('docs/openapi/users-create.yaml', $rebuilt->openapiFragmentPath);
        $this->assertSame('a', $rebuilt->filesModified[0]->contentBefore);
    }

    public function testWithRevertedAtAppendsAuditTrailImmutably(): void
    {
        $original = JournalEntry::scaffold(
            command: 'cmd',
            specPath: 'spec.yaml',
            specContent: 'x',
            scaffoldVersion: '1.0',
            timestamp: new DateTimeImmutable('2026-05-27T10:00:00Z'),
        );
        $revertTime = new DateTimeImmutable('2026-05-27T11:00:00Z');

        $reverted = $original->withRevertedAt($revertTime);

        $this->assertFalse($original->isReverted(), 'Original must stay unchanged (immutable).');
        $this->assertTrue($reverted->isReverted());
        $this->assertSame($revertTime->getTimestamp(), $reverted->revertedAt?->getTimestamp());
    }

    public function testFromArrayRejectsMissingFields(): void
    {
        $this->expectException(JournalException::class);
        JournalEntry::fromArray(['id' => 'x']);
    }

    public function testFileSnapshotRejectsEmptyPath(): void
    {
        $this->expectException(JournalException::class);
        new FileSnapshot(path: '', shaBefore: null, shaAfter: null, sizeBytes: null);
    }
}

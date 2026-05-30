<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Journal;

use Altair\Scaffold\Journal\Exception\JournalException;
use DateTimeImmutable;
use DateTimeInterface;
use JsonException;

/**
 * One immutable journal record written to
 * `.altair/journal/<timestamp>-<short-sha>.json`.
 *
 * Self-contained: the spec content is embedded so an entry can be
 * replayed even if the original spec file is later edited or deleted.
 *
 * `revertedAt` is appended (via {@see self::withRevertedAt()}) when a
 * subsequent `spec rewind` undoes this entry — the entry itself stays
 * on disk so rewind history is part of the audit trail.
 *
 * @phpstan-type SpecInline array{ path: string, sha256: string, content_inline: string }
 */
final readonly class JournalEntry
{
    public const string VERSION = '1.0';

    /**
     * @param list<FileSnapshot>   $filesCreated
     * @param list<FileSnapshot>   $filesModified
     * @param list<string>         $filesSkipped
     * @param SpecInline           $spec
     */
    public function __construct(
        public string $id,
        public OperationKind $operation,
        public string $command,
        public DateTimeImmutable $timestamp,
        public ?string $user,
        public array $spec,
        public string $scaffoldVersion,
        public array $filesCreated = [],
        public array $filesModified = [],
        public array $filesSkipped = [],
        public ?string $openapiFragmentPath = null,
        public ?DateTimeImmutable $revertedAt = null,
        public ?string $targetEntryId = null,
    ) {
        if ($id === '') {
            throw new JournalException('JournalEntry id must not be empty.');
        }

        foreach (['path', 'sha256', 'content_inline'] as $required) {
            if (!isset($spec[$required]) || !\is_string($spec[$required])) {
                throw new JournalException(\sprintf('JournalEntry spec is missing required string field "%s".', $required));
            }
        }
    }

    /**
     * Named-constructor for a scaffold operation. The id is the
     * `<timestamp>-<short-sha>` form used as the on-disk filename stem.
     *
     * @param list<FileSnapshot>   $filesCreated
     * @param list<FileSnapshot>   $filesModified
     * @param list<string>         $filesSkipped
     */
    public static function scaffold(
        string $command,
        string $specPath,
        string $specContent,
        string $scaffoldVersion,
        array $filesCreated = [],
        array $filesModified = [],
        array $filesSkipped = [],
        ?string $openapiFragmentPath = null,
        ?DateTimeImmutable $timestamp = null,
        ?string $user = null,
    ): self {
        $ts = $timestamp ?? new DateTimeImmutable('now');
        $specSha = hash('sha256', $specContent);

        return new self(
            id: self::buildId($ts, $specSha),
            operation: OperationKind::Scaffold,
            command: $command,
            timestamp: $ts,
            user: $user ?? self::resolveUser(),
            spec: [
                'path' => $specPath,
                'sha256' => $specSha,
                'content_inline' => $specContent,
            ],
            scaffoldVersion: $scaffoldVersion,
            filesCreated: $filesCreated,
            filesModified: $filesModified,
            filesSkipped: $filesSkipped,
            openapiFragmentPath: $openapiFragmentPath,
        );
    }

    /**
     * Named-constructor for an `openapi:import` operation. The "spec" field
     * stores the OpenAPI source document so rewind/replay can reconstruct
     * the entire import even if the source file is later moved or deleted.
     *
     * Uses {@see OperationKind::Scaffold} on disk so existing
     * `journal:rewind` / `journal:replay` logic — which dispatches on the
     * recorded file lists, not the operation kind — keeps working without
     * needing a parallel rewind path.
     *
     * @param list<FileSnapshot>   $filesCreated
     * @param list<FileSnapshot>   $filesModified
     * @param list<string>         $filesSkipped
     */
    public static function openApiImport(
        string $command,
        string $documentPath,
        string $documentContent,
        string $scaffoldVersion,
        array $filesCreated = [],
        array $filesModified = [],
        array $filesSkipped = [],
        ?DateTimeImmutable $timestamp = null,
        ?string $user = null,
    ): self {
        $ts = $timestamp ?? new DateTimeImmutable('now');
        $sha = hash('sha256', $documentContent);

        return new self(
            id: self::buildId($ts, $sha),
            operation: OperationKind::Scaffold,
            command: $command,
            timestamp: $ts,
            user: $user ?? self::resolveUser(),
            spec: [
                'path' => $documentPath,
                'sha256' => $sha,
                'content_inline' => $documentContent,
            ],
            scaffoldVersion: $scaffoldVersion,
            filesCreated: $filesCreated,
            filesModified: $filesModified,
            filesSkipped: $filesSkipped,
        );
    }

    public function withRevertedAt(DateTimeImmutable $when): self
    {
        return new self(
            id: $this->id,
            operation: $this->operation,
            command: $this->command,
            timestamp: $this->timestamp,
            user: $this->user,
            spec: $this->spec,
            scaffoldVersion: $this->scaffoldVersion,
            filesCreated: $this->filesCreated,
            filesModified: $this->filesModified,
            filesSkipped: $this->filesSkipped,
            openapiFragmentPath: $this->openapiFragmentPath,
            revertedAt: $when,
            targetEntryId: $this->targetEntryId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'version' => self::VERSION,
            'operation' => $this->operation->value,
            'id' => $this->id,
            'command' => $this->command,
            'timestamp' => $this->timestamp->format(DateTimeInterface::RFC3339_EXTENDED),
            'user' => $this->user,
            'spec' => $this->spec,
            'scaffold_version' => $this->scaffoldVersion,
            'files_created' => array_map(static fn(FileSnapshot $s): array => $s->toArray(), $this->filesCreated),
            'files_modified' => array_map(static fn(FileSnapshot $s): array => $s->toArray(), $this->filesModified),
            'files_skipped' => $this->filesSkipped,
        ];

        if ($this->openapiFragmentPath !== null) {
            $out['openapi_fragment_path'] = $this->openapiFragmentPath;
        }

        if ($this->revertedAt instanceof DateTimeImmutable) {
            $out['reverted_at'] = $this->revertedAt->format(DateTimeInterface::RFC3339_EXTENDED);
        }

        if ($this->targetEntryId !== null) {
            $out['target_entry_id'] = $this->targetEntryId;
        }

        return $out;
    }

    public function toJson(): string
    {
        try {
            return json_encode(
                $this->toArray(),
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $jsonException) {
            throw new JournalException('JournalEntry is not JSON-encodable: ' . $jsonException->getMessage(), 0, $jsonException);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['id', 'operation', 'command', 'timestamp', 'spec', 'scaffold_version'] as $required) {
            if (!\array_key_exists($required, $data)) {
                throw new JournalException(\sprintf('JournalEntry missing required field "%s".', $required));
            }
        }

        $timestamp = $data['timestamp'];
        $parsed = \is_string($timestamp)
            ? (DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $timestamp)
                ?: DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339, $timestamp))
            : false;
        if ($parsed === false || $parsed === null) {
            throw new JournalException(\sprintf('Invalid timestamp "%s".', (string) $timestamp));
        }

        $revertedAt = null;
        if (isset($data['reverted_at']) && \is_string($data['reverted_at'])) {
            $rv = DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $data['reverted_at'])
                ?: DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339, $data['reverted_at']);
            $revertedAt = $rv === false ? null : $rv;
        }

        /** @var array<int, array<string, mixed>> $createdRaw */
        $createdRaw = \is_array($data['files_created'] ?? null) ? $data['files_created'] : [];
        /** @var array<int, array<string, mixed>> $modifiedRaw */
        $modifiedRaw = \is_array($data['files_modified'] ?? null) ? $data['files_modified'] : [];
        /** @var array<int, mixed> $skippedRaw */
        $skippedRaw = \is_array($data['files_skipped'] ?? null) ? $data['files_skipped'] : [];

        /** @var array<string, mixed> $specData */
        $specData = \is_array($data['spec']) ? $data['spec'] : [];

        return new self(
            id: (string) $data['id'],
            operation: OperationKind::from((string) $data['operation']),
            command: (string) $data['command'],
            timestamp: $parsed,
            user: isset($data['user']) && \is_string($data['user']) ? $data['user'] : null,
            spec: [
                'path' => (string) ($specData['path'] ?? ''),
                'sha256' => (string) ($specData['sha256'] ?? ''),
                'content_inline' => (string) ($specData['content_inline'] ?? ''),
            ],
            scaffoldVersion: (string) $data['scaffold_version'],
            filesCreated: array_map(FileSnapshot::fromArray(...), array_values($createdRaw)),
            filesModified: array_map(FileSnapshot::fromArray(...), array_values($modifiedRaw)),
            filesSkipped: array_values(array_map(static fn(mixed $p): string => (string) $p, $skippedRaw)),
            openapiFragmentPath: isset($data['openapi_fragment_path']) && \is_string($data['openapi_fragment_path']) ? $data['openapi_fragment_path'] : null,
            revertedAt: $revertedAt,
            targetEntryId: isset($data['target_entry_id']) && \is_string($data['target_entry_id']) ? $data['target_entry_id'] : null,
        );
    }

    public function isReverted(): bool
    {
        return $this->revertedAt instanceof DateTimeImmutable;
    }

    /**
     * `<YYYYMMDDTHHMMSSZ>-<first-8-of-sha>` — sortable, filesystem-safe.
     */
    private static function buildId(DateTimeImmutable $when, string $sha): string
    {
        return $when->format('Ymd\\THis\\Z') . '-' . substr($sha, 0, 8);
    }

    private static function resolveUser(): ?string
    {
        $user = getenv('USER');
        if ($user !== false && $user !== '') {
            return $user;
        }

        $user = getenv('USERNAME'); // Windows
        if ($user !== false && $user !== '') {
            return $user;
        }

        return null;
    }
}

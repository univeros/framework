<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events;

use Altair\Events\Exception\InvalidArgumentException;

/**
 * Immutable map of "what changed" buckets keyed by verb.
 *
 * The shape is intentionally open-ended so each kind (scaffold, migration,
 * rewind, ...) can name its own buckets (`created`, `modified`, `applied`,
 * `restored`, `deleted`, `skipped`, ...) without locking the type to one
 * vocabulary. The Recorder serialises this as a plain JSON object.
 *
 * Heavy diffs that don't fit cleanly into a one-line event live in a
 * companion snapshot file referenced by {@see $snapshotRef}.
 */
final readonly class Changes
{
    /**
     * @param array<string, list<string>> $buckets Verb (e.g. "created") → list of identifiers (paths, migration names).
     */
    public function __construct(
        public array $buckets = [],
        public ?string $snapshotRef = null,
    ) {
        foreach ($buckets as $verb => $entries) {
            if (!\is_string($verb) || $verb === '') {
                throw new InvalidArgumentException('Changes bucket keys must be non-empty strings.');
            }
            foreach ($entries as $entry) {
                if (!\is_string($entry)) {
                    throw new InvalidArgumentException(
                        \sprintf("Changes bucket '%s' must contain only strings.", $verb),
                    );
                }
            }
        }
    }

    public function withSnapshotRef(string $ref): self
    {
        return new self($this->buckets, $ref);
    }

    public function withBucket(string $verb, string ...$entries): self
    {
        $buckets = $this->buckets;
        $buckets[$verb] = array_values(array_merge($buckets[$verb] ?? [], $entries));

        return new self($buckets, $this->snapshotRef);
    }

    public function isEmpty(): bool
    {
        if ($this->snapshotRef !== null) {
            return false;
        }

        foreach ($this->buckets as $entries) {
            if ($entries !== []) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = $this->buckets;
        if ($this->snapshotRef !== null) {
            $out['snapshot_ref'] = $this->snapshotRef;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $snapshotRef = null;
        $buckets = [];
        foreach ($data as $key => $value) {
            if ($key === 'snapshot_ref') {
                if ($value !== null && !\is_string($value)) {
                    throw new InvalidArgumentException('snapshot_ref must be a string.');
                }
                $snapshotRef = $value;
                continue;
            }

            if (!\is_array($value)) {
                throw new InvalidArgumentException(
                    \sprintf("Changes bucket '%s' must be a list.", $key),
                );
            }
            $buckets[$key] = array_values($value);
        }

        return new self($buckets, $snapshotRef);
    }
}

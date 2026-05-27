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

/**
 * Immutable per-file record embedded in a {@see JournalEntry}.
 *
 * `created` files carry only the post-scaffold sha + size.
 * `modified` files carry sha-before, sha-after, and the unified diff —
 * everything required to reverse the edit without touching the spec.
 */
final readonly class FileSnapshot
{
    public function __construct(
        public string $path,
        public ?string $shaBefore,
        public ?string $shaAfter,
        public ?int $sizeBytes,
        public ?string $diff = null,
        public ?string $contentBefore = null,
    ) {
        if ($path === '') {
            throw new JournalException('FileSnapshot path must not be empty.');
        }
    }

    public static function created(string $path, string $shaAfter, int $sizeBytes): self
    {
        return new self(
            path: $path,
            shaBefore: null,
            shaAfter: $shaAfter,
            sizeBytes: $sizeBytes,
        );
    }

    public static function modified(string $path, string $shaBefore, string $shaAfter, string $diff, ?string $contentBefore = null): self
    {
        return new self(
            path: $path,
            shaBefore: $shaBefore,
            shaAfter: $shaAfter,
            sizeBytes: null,
            diff: $diff,
            contentBefore: $contentBefore,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = ['path' => $this->path];
        if ($this->shaBefore !== null) {
            $out['sha256_before'] = $this->shaBefore;
        }

        if ($this->shaAfter !== null) {
            $out['sha256_after'] = $this->shaAfter;
        }

        if ($this->sizeBytes !== null) {
            $out['size_bytes'] = $this->sizeBytes;
        }

        if ($this->diff !== null) {
            $out['diff'] = $this->diff;
        }

        if ($this->contentBefore !== null) {
            $out['content_before'] = $this->contentBefore;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['path']) || !\is_string($data['path'])) {
            throw new JournalException('FileSnapshot requires a string "path".');
        }

        return new self(
            path: $data['path'],
            shaBefore: isset($data['sha256_before']) && \is_string($data['sha256_before']) ? $data['sha256_before'] : null,
            shaAfter: isset($data['sha256_after']) && \is_string($data['sha256_after']) ? $data['sha256_after'] : null,
            sizeBytes: isset($data['size_bytes']) && \is_int($data['size_bytes']) ? $data['size_bytes'] : null,
            diff: isset($data['diff']) && \is_string($data['diff']) ? $data['diff'] : null,
            contentBefore: isset($data['content_before']) && \is_string($data['content_before']) ? $data['content_before'] : null,
        );
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Model;

/**
 * A reference to a symbol at a concrete location.
 *
 * `$fqn` is the symbol being referenced (the target); `$file` / `$line` are
 * where the reference occurs; `$context` is the enclosing method or function
 * when known, so callers-of queries can report a useful "from" location.
 */
final readonly class Usage
{
    public function __construct(
        public string $fqn,
        public string $file,
        public int $line,
        public UsageKind $kind,
        public ?string $context = null,
    ) {}

    /**
     * @return array{file: string, line: int, usage_kind: string, context: ?string}
     */
    public function toArray(): array
    {
        return [
            'file' => $this->file,
            'line' => $this->line,
            'usage_kind' => $this->kind->value,
            'context' => $this->context,
        ];
    }
}

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
 * A single declared symbol: a class-like, a method, a property, or a constant.
 *
 * The fully-qualified name is the unique key. Members use the
 * `Declaring\Class::member` form (a `::` join), so a method and the class that
 * declares it are distinct, queryable rows.
 */
final readonly class Symbol
{
    public function __construct(
        public string $fqn,
        public SymbolKind $kind,
        public string $file,
        public int $line,
        public ?string $visibility = null,
        public bool $isReadonly = false,
        public bool $isStatic = false,
    ) {}

    /**
     * @return array{fqn: string, kind: string, file: string, line: int, visibility: ?string, is_readonly: bool, is_static: bool}
     */
    public function toArray(): array
    {
        return [
            'fqn' => $this->fqn,
            'kind' => $this->kind->value,
            'file' => $this->file,
            'line' => $this->line,
            'visibility' => $this->visibility,
            'is_readonly' => $this->isReadonly,
            'is_static' => $this->isStatic,
        ];
    }
}

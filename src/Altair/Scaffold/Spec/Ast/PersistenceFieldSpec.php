<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Spec\Ast;

/**
 * A single column on a persisted entity.
 *
 * `type` is the Cycle column type (e.g. "string", "int", "uuid", "datetime",
 * "json", "enum"). For enum fields, `of` references a PHP enum class.
 */
final readonly class PersistenceFieldSpec
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $primary = false,
        public bool $nullable = false,
        public bool $unique = false,
        public bool $hasDefault = false,
        public mixed $default = null,
        public ?string $of = null,
    ) {}

    public function isEnum(): bool
    {
        return $this->type === 'enum' && $this->of !== null;
    }
}

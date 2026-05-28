<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Schema;

/**
 * Outcome of validating a tool's input against its JSON Schema.
 */
final readonly class SchemaValidationResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public bool $valid,
        public array $errors = [],
    ) {}

    public function message(): string
    {
        return $this->errors === []
            ? 'Invalid input.'
            : 'Invalid input: ' . implode('; ', $this->errors);
    }
}

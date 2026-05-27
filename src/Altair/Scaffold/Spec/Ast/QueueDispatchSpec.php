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
 * Describes a single queue dispatch: which message to emit, what fields
 * it carries, and which transport routes it.
 *
 * One spec file may declare any number of these; the scaffolder emits a
 * message DTO + handler stub + handler test for each.
 */
final readonly class QueueDispatchSpec
{
    /**
     * @param string             $name      Key used in YAML (e.g. "on_create")
     * @param string             $message   FQCN of the message class to emit
     * @param array<string, string> $fields  Field name => PHP-ish type (string|int|float|bool)
     * @param ?string            $transport Optional transport name (null = use bus default routing)
     */
    public function __construct(
        public string $name,
        public string $message,
        public array $fields = [],
        public ?string $transport = null,
    ) {}
}

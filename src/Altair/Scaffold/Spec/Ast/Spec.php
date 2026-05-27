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
 * Root AST node for a single endpoint specification.
 */
final readonly class Spec
{
    /**
     * @param list<InputFieldSpec>     $inputs
     * @param list<OutputResponseSpec> $outputs
     * @param list<QueueDispatchSpec>  $queue
     */
    public function __construct(
        public EndpointSpec $endpoint,
        public array $inputs,
        public array $outputs,
        public DomainSpec $domain,
        public string $sourcePath = '',
        public ?PersistenceSpec $persistence = null,
        public array $queue = [],
    ) {}

    /**
     * Derives a PascalCase name to use as a prefix for emitted artifacts.
     *
     * Strategy: take the domain class short-name (e.g. "App\\User\\CreateUser" -> "CreateUser").
     */
    public function artifactName(): string
    {
        $parts = explode('\\', $this->domain->class);

        return end($parts) ?: 'Endpoint';
    }

    public function hasPersistence(): bool
    {
        return $this->persistence instanceof PersistenceSpec;
    }
}

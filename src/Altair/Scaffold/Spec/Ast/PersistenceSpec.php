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
 * The optional `persistence:` block on a Spec.
 *
 * When present, the scaffolder also emits an entity class, a repository
 * class, and a migration file.
 */
final readonly class PersistenceSpec
{
    public function __construct(
        public PersistenceEntitySpec $entity,
        public string $repository,
    ) {}
}

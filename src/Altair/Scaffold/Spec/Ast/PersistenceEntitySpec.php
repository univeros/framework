<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Spec\Ast;

final readonly class PersistenceEntitySpec
{
    /**
     * @param list<PersistenceFieldSpec> $fields
     */
    public function __construct(
        public string $class,
        public string $table,
        public array $fields,
    ) {}

    public function primaryField(): ?PersistenceFieldSpec
    {
        foreach ($this->fields as $field) {
            if ($field->primary) {
                return $field;
            }
        }

        return null;
    }
}

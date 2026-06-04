<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Spec\Ast;

final readonly class InputFieldSpec
{
    /**
     * @param list<string> $rules
     * @param list<self>   $fields Child fields for a nested object (`type: object`)
     *                             or the item shape of an array of objects
     *                             (`type: array` with `fields`). Empty for scalars.
     */
    public function __construct(
        public string $name,
        public string $type,
        public array $rules = [],
        public bool $sensitive = false,
        public ?string $of = null,
        public mixed $default = null,
        public bool $hasDefault = false,
        public array $fields = [],
    ) {}

    public function isRequired(): bool
    {
        return \in_array('required', $this->rules, true);
    }

    public function isEnum(): bool
    {
        return $this->type === 'enum' && $this->of !== null;
    }

    public function isObject(): bool
    {
        return $this->type === 'object';
    }

    public function isArrayOfObjects(): bool
    {
        return $this->type === 'array' && $this->fields !== [];
    }
}

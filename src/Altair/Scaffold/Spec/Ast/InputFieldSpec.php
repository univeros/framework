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
    public const string IN_BODY = 'body';

    /**
     * @param list<string> $rules
     * @param list<self>   $fields   Child fields for a nested object (`type: object`)
     *                               or the item shape of an array of objects
     *                               (`type: array` with `fields`). Empty for scalars.
     * @param string       $location Where the value comes from: `body` (default),
     *                               `path`, `query`, `header`, or `cookie`. Non-body
     *                               inputs export as OpenAPI `parameters`.
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
        public string $location = self::IN_BODY,
    ) {}

    public function isParameter(): bool
    {
        return $this->location !== self::IN_BODY;
    }

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

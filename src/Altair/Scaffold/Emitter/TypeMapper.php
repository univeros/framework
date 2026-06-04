<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Emitter;

use Altair\Scaffold\Spec\Ast\InputFieldSpec;

/**
 * Maps spec types (strings like "string", "enum", "int") to:
 *
 *   - a native PHP type usable in a typed property/parameter
 *   - an OpenAPI 3.1 type fragment usable inside a schema block
 */
final class TypeMapper
{
    public function toPhpType(InputFieldSpec $field): string
    {
        if ($field->isEnum()) {
            return '\\' . ltrim($field->of ?? '', '\\');
        }

        return match (strtolower($field->type)) {
            'int', 'integer' => 'int',
            'float'          => 'float',
            'bool', 'boolean' => 'bool',
            'array', 'object' => 'array',
            default          => 'string',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toOpenApiSchema(InputFieldSpec $field): array
    {
        if ($field->isEnum()) {
            return ['type' => 'string'];
        }

        return match (strtolower($field->type)) {
            'int', 'integer' => $this->applyConstraints(['type' => 'integer'], $field),
            'float'          => $this->applyConstraints(['type' => 'number', 'format' => 'float'], $field),
            'bool', 'boolean' => ['type' => 'boolean'],
            'object'         => $this->objectSchema($field),
            'array'          => $this->arraySchema($field),
            default          => $this->applyConstraints(['type' => 'string'], $field),
        };
    }

    /**
     * Returns true when this field appears as `required` in OpenAPI requestBody.
     */
    public function isRequired(InputFieldSpec $field): bool
    {
        return $field->isRequired() && !$field->hasDefault;
    }

    /**
     * Adds the JSON-Schema constraints encoded in a field's validation rules —
     * the inverse of the import-side rule mapping, so the two round-trip:
     * `email`→`format: email`, `regex:p`→`pattern: p`, `in:a,b`→`enum`,
     * `min`/`max`→`minLength`/`maxLength` (strings) or `minimum`/`maximum`.
     *
     * @param  array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function applyConstraints(array $schema, InputFieldSpec $field): array
    {
        $isString = ($schema['type'] ?? '') === 'string';
        foreach ($field->rules as $rule) {
            $schema = [...$schema, ...$this->constraintFromRule($rule, $isString)];
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function constraintFromRule(string $rule, bool $isString): array
    {
        [$name, $arg] = array_pad(explode(':', $rule, 2), 2, '');

        return match ($name) {
            'email'    => ['format' => 'email'],
            'url'      => ['format' => 'uri'],
            'datetime' => ['format' => 'date-time'],
            'regex'    => $arg !== '' ? ['pattern' => $arg] : [],
            'in'       => $arg !== '' ? ['enum' => explode(',', $arg)] : [],
            'min'      => $isString ? ['minLength' => (int) $arg] : ['minimum' => $this->numericArg($arg)],
            'max'      => $isString ? ['maxLength' => (int) $arg] : ['maximum' => $this->numericArg($arg)],
            default    => [],
        };
    }

    private function numericArg(string $arg): int|float
    {
        return str_contains($arg, '.') ? (float) $arg : (int) $arg;
    }

    /**
     * A nested object: recurse into child fields for `properties`, carrying
     * the per-child `required` flags into the OpenAPI `required` array.
     *
     * @return array<string, mixed>
     */
    private function objectSchema(InputFieldSpec $field): array
    {
        $properties = [];
        $required = [];
        foreach ($field->fields as $child) {
            $properties[$child->name] = $this->toOpenApiSchema($child);
            if ($this->isRequired($child)) {
                $required[] = $child->name;
            }
        }

        $schema = ['type' => 'object', 'properties' => $properties];
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * An array: object items when the field declares `fields` (array of
     * objects), otherwise the existing scalar-item default.
     *
     * @return array<string, mixed>
     */
    private function arraySchema(InputFieldSpec $field): array
    {
        $items = $field->fields !== []
            ? $this->objectSchema($field)
            : ['type' => 'string'];

        return ['type' => 'array', 'items' => $items];
    }
}

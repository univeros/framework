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
            'int', 'integer' => ['type' => 'integer'],
            'float'          => ['type' => 'number', 'format' => 'float'],
            'bool', 'boolean' => ['type' => 'boolean'],
            'object'         => $this->objectSchema($field),
            'array'          => $this->arraySchema($field),
            default          => ['type' => 'string'],
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

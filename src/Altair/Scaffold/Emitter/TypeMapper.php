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
            'array'          => 'array',
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
            'array'          => ['type' => 'array', 'items' => ['type' => 'string']],
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
}

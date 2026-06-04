<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Spec\Emitter;

use Altair\Scaffold\Sdk\Model\OpenApiDocument;
use Altair\Scaffold\Sdk\Model\OperationModel;
use Altair\Scaffold\Sdk\Model\ParameterModel;
use Altair\Scaffold\Sdk\Model\ResponseModel;
use Altair\Scaffold\Sdk\Model\SchemaType;
use Altair\Scaffold\Spec\Emitter\Exception\UnmappableSchemaException;

/**
 * Translates the language-neutral {@see SchemaType} tree into the flat
 * field-list shape Altair YAML specs use.
 *
 * Inputs are scalar-only by design (Altair's input layer maps to validated
 * primitives), so nested objects and arrays of objects raise
 * {@see UnmappableSchemaException}. Output bodies allow richer types
 * (FQCN references, generics) because the scaffolder hands them through to
 * the responder unchanged.
 */
final readonly class SchemaMapper
{
    /** Refs we have followed during the current resolution — guards against cycles. */
    private const int MAX_REF_DEPTH = 8;

    /**
     * Inline object-nesting ceiling. `resolveRef` already bounds `$ref` chains;
     * this bounds *inline* nesting so a hostile or pathological document raises
     * a clean {@see UnmappableSchemaException} (which `--skip-unmappable` can
     * absorb) instead of exhausting the PHP call stack.
     */
    private const int MAX_NESTING_DEPTH = 32;

    public function __construct(
        private string $appNamespace = 'App',
    ) {}

    /**
     * Combined path-parameter + request-body field list for an operation.
     *
     * @return list<array<string, mixed>>
     */
    public function inputFields(OpenApiDocument $document, OperationModel $operation): array
    {
        $fields = [];

        foreach ($this->operationParameters($operation) as $parameter) {
            $fields[] = $this->parameterField($parameter);
        }

        $requestBody = $operation->requestBody;
        if ($requestBody instanceof SchemaType) {
            $pointer = $this->requestBodyPointer($operation);
            $bodySchema = $this->resolveRef($document, $requestBody, $pointer);
            foreach ($this->bodyInputFields($document, $bodySchema, $pointer) as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Builds the `output:` map (status => body) for every response carrying a
     * parseable schema. Statuses with no schema (e.g. 204 No Content, 404
     * description-only) are skipped because Altair's output block can't
     * represent an empty body.
     *
     * @return array<int, array<string, string>>
     */
    public function outputs(OpenApiDocument $document, OperationModel $operation): array
    {
        $outputs = [];

        foreach ($operation->responses as $response) {
            if (!$response->statusIsNumeric()) {
                continue;
            }

            if (!$response->schema instanceof SchemaType) {
                continue;
            }

            $status = (int) $response->status;
            $outputs[$status] = $this->responseBody($document, $response, $this->responsePointer($operation, $response));
        }

        return $outputs;
    }

    /**
     * Declared parameters, falling back to bare path-parameter names for an
     * {@see OperationModel} built without a `parameters` list (older callers).
     *
     * @return list<ParameterModel>
     */
    private function operationParameters(OperationModel $operation): array
    {
        if ($operation->parameters !== []) {
            return $operation->parameters;
        }

        return array_map(
            static fn(string $name): ParameterModel => new ParameterModel($name, ParameterModel::IN_PATH, true),
            $operation->pathParameters,
        );
    }

    /**
     * A path/query/header/cookie parameter becomes an input field tagged with
     * its `in` location (so it exports back to an OpenAPI parameter). Parameters
     * are scalars/enums/arrays in practice; anything richer falls back to string.
     *
     * @return array<string, mixed>
     */
    private function parameterField(ParameterModel $parameter): array
    {
        $rules = $parameter->required ? ['required'] : [];
        $type = 'string';
        $schema = $parameter->schema;

        if ($schema instanceof SchemaType) {
            if ($schema->kind === SchemaType::ENUM && $schema->enumValues !== []) {
                $rules[] = 'in:' . implode(',', $schema->enumValues);
            } else {
                $type = match ($schema->kind) {
                    SchemaType::ARRAY => 'array',
                    SchemaType::SCALAR => $this->inputScalarType((string) $schema->scalarType),
                    default => 'string',
                };
                $rules = [...$rules, ...$this->constraintRules($schema)];
            }
        }

        return [
            'name' => $parameter->name,
            'type' => $type,
            'in' => $parameter->in,
            'rules' => $rules,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function responseBody(OpenApiDocument $document, ResponseModel $response, string $pointer): array
    {
        \assert($response->schema instanceof SchemaType);

        // Top-level $ref preserves the abstraction — wrap rather than inline.
        if ($response->schema->kind === SchemaType::REF) {
            $refName = (string) $response->schema->ref;

            return [$this->lowercaseFirst($refName) => $this->refFqcn($refName)];
        }

        $schema = $this->resolveRef($document, $response->schema, $pointer);

        if ($schema->kind === SchemaType::OBJECT) {
            $body = [];
            foreach ($schema->properties as $name => $property) {
                $body[(string) $name] = $this->outputType($document, $property['schema'], $pointer . '/properties/' . $name);
            }

            return $body;
        }

        return ['result' => $this->outputType($document, $schema, $pointer)];
    }

    /**
     * Maps a request body to input fields. An object body becomes one field per
     * property. A top-level **array** body — e.g. the Petstore's
     * `POST /user/createWithList`, a bare array of `User` — becomes a single
     * `items` array field carrying the element shape, since Altair inputs are a
     * named field list and the list needs a name. Any other root kind is
     * unmappable.
     *
     * @return list<array<string, mixed>>
     */
    private function bodyInputFields(OpenApiDocument $document, SchemaType $schema, string $pointer): array
    {
        return match ($schema->kind) {
            SchemaType::OBJECT => $this->mapProperties($document, $schema, $pointer),
            SchemaType::ARRAY => [$this->arrayInputField($document, 'items', $schema, [], $pointer)],
            default => throw new UnmappableSchemaException(
                $pointer,
                'request body must be an object or array (got ' . $schema->kind . ').',
            ),
        };
    }

    /**
     * Maps every property of an object schema into the flat field-array shape,
     * recursing through {@see inputField} so nested objects nest. Shared by the
     * top-level request body and nested objects.
     *
     * @return list<array<string, mixed>>
     */
    private function mapProperties(OpenApiDocument $document, SchemaType $schema, string $pointer, int $depth = 0): array
    {
        if ($depth > self::MAX_NESTING_DEPTH) {
            throw new UnmappableSchemaException(
                $pointer,
                \sprintf('input object nesting exceeds the maximum depth of %d.', self::MAX_NESTING_DEPTH),
            );
        }

        $fields = [];
        foreach ($schema->properties as $name => $property) {
            $propertyPointer = $pointer . '/properties/' . $name;
            $fields[] = $this->inputField($document, (string) $name, $property['schema'], $property['required'], $propertyPointer, $depth);
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private function inputField(OpenApiDocument $document, string $name, SchemaType $schema, bool $required, string $pointer, int $depth = 0): array
    {
        $rules = $required ? ['required'] : [];
        $resolved = $this->resolveRef($document, $schema, $pointer);

        return match ($resolved->kind) {
            SchemaType::SCALAR => $this->scalarInputField($name, $resolved, $rules),
            SchemaType::ENUM => $this->enumInputField($name, $resolved, $rules),
            SchemaType::ARRAY => $this->arrayInputField($document, $name, $resolved, $rules, $pointer, $depth),
            SchemaType::OBJECT => $this->objectInputField($document, $name, $resolved, $rules, $pointer, $depth),
            default => throw new UnmappableSchemaException(
                $pointer,
                \sprintf("unsupported input kind '%s'.", $resolved->kind),
            ),
        };
    }

    /**
     * A nested object becomes `{type: object, rules, fields: [...]}` where
     * `fields` is the recursively-mapped child property list. Symmetric with
     * the forward {@see \Altair\Scaffold\Emitter\TypeMapper::objectSchema()}.
     *
     * @param  list<string>          $rules
     * @return array<string, mixed>
     */
    private function objectInputField(OpenApiDocument $document, string $name, SchemaType $schema, array $rules, string $pointer, int $depth = 0): array
    {
        return [
            'name' => $name,
            'type' => 'object',
            'rules' => $rules,
            'fields' => $this->mapProperties($document, $schema, $pointer, $depth + 1),
        ];
    }

    /**
     * @param  list<string>          $rules
     * @return array<string, mixed>
     */
    private function scalarInputField(string $name, SchemaType $schema, array $rules): array
    {
        return [
            'name' => $name,
            'type' => $this->inputScalarType((string) $schema->scalarType),
            'rules' => [...$rules, ...$this->constraintRules($schema)],
        ];
    }

    /**
     * Translates a scalar schema's `format` + JSON-Schema validation keywords
     * into Altair validation rules. The inverse of the forward
     * {@see \Altair\Scaffold\Emitter\TypeMapper}'s rule → constraint mapping, so
     * the pair round-trips.
     *
     * @return list<string>
     */
    private function constraintRules(SchemaType $schema): array
    {
        $rules = [];

        $formatRule = match (strtolower((string) $schema->format)) {
            'email' => 'email',
            'uri', 'url' => 'url',
            'ipv4', 'ipv6', 'ip' => 'ip',
            'date-time', 'date', 'time' => 'datetime',
            default => null,
        };
        if ($formatRule !== null) {
            $rules[] = $formatRule;
        }

        $constraints = $schema->constraints;
        if (isset($constraints['minLength'])) {
            $rules[] = 'min:' . $constraints['minLength'];
        }

        if (isset($constraints['maxLength'])) {
            $rules[] = 'max:' . $constraints['maxLength'];
        }

        if (isset($constraints['minimum'])) {
            $rules[] = 'min:' . $constraints['minimum'];
        }

        if (isset($constraints['maximum'])) {
            $rules[] = 'max:' . $constraints['maximum'];
        }

        if (isset($constraints['pattern'])) {
            $rules[] = 'regex:' . $constraints['pattern'];
        }

        return $rules;
    }

    /**
     * @param  list<string>          $rules
     * @return array<string, mixed>
     */
    private function enumInputField(string $name, SchemaType $schema, array $rules): array
    {
        if ($schema->enumValues !== []) {
            $rules[] = 'in:' . implode(',', $schema->enumValues);
        }

        return [
            'name' => $name,
            'type' => 'string',
            'rules' => $rules,
        ];
    }

    /**
     * @param  list<string>          $rules
     * @return array<string, mixed>
     */
    private function arrayInputField(OpenApiDocument $document, string $name, SchemaType $schema, array $rules, string $pointer, int $depth = 0): array
    {
        if ($schema->items instanceof SchemaType) {
            $items = $this->resolveRef($document, $schema->items, $pointer . '/items');
            if ($items->kind === SchemaType::OBJECT) {
                // Array of objects: carry the item object's properties as `fields`.
                return [
                    'name' => $name,
                    'type' => 'array',
                    'rules' => $rules,
                    'fields' => $this->mapProperties($document, $items, $pointer . '/items', $depth + 1),
                ];
            }
        }

        return [
            'name' => $name,
            'type' => 'array',
            'rules' => $rules,
        ];
    }

    private function outputType(OpenApiDocument $document, SchemaType $schema, string $pointer): string
    {
        if ($schema->kind === SchemaType::REF) {
            $refName = (string) $schema->ref;
            $resolved = $this->resolveRef($document, $schema, $pointer);

            // Refs to objects keep the FQCN abstraction; refs to scalars/enums
            // render as the underlying type so consumers see what's on the wire.
            return $resolved->kind === SchemaType::OBJECT
                ? $this->refFqcn($refName)
                : $this->outputType($document, $resolved, $pointer);
        }

        return match ($schema->kind) {
            SchemaType::SCALAR => $this->outputScalarType((string) $schema->scalarType),
            SchemaType::ENUM => 'string',
            SchemaType::ARRAY => $this->outputArrayType($document, $schema, $pointer),
            SchemaType::OBJECT => 'array<string, mixed>',
            SchemaType::MIXED => 'mixed',
            default => throw new UnmappableSchemaException(
                $pointer,
                \sprintf("unsupported output kind '%s'.", $schema->kind),
            ),
        };
    }

    private function outputArrayType(OpenApiDocument $document, SchemaType $schema, string $pointer): string
    {
        if (!$schema->items instanceof SchemaType) {
            return 'list<mixed>';
        }

        $inner = $this->outputType($document, $schema->items, $pointer . '/items');

        return 'list<' . $inner . '>';
    }

    private function inputScalarType(string $scalarType): string
    {
        return match ($scalarType) {
            'integer' => 'int',
            'number' => 'float',
            'boolean' => 'bool',
            default => 'string',
        };
    }

    private function outputScalarType(string $scalarType): string
    {
        return match ($scalarType) {
            'integer' => 'int',
            'number' => 'float',
            'boolean' => 'bool',
            default => 'string',
        };
    }

    private function refFqcn(string $refName): string
    {
        return $this->appNamespace . '\\' . $refName . '\\' . $refName;
    }

    private function lowercaseFirst(string $value): string
    {
        return $value === '' ? $value : strtolower($value[0]) . substr($value, 1);
    }

    private function resolveRef(
        OpenApiDocument $document,
        SchemaType $schema,
        string $pointer,
        int $depth = 0,
    ): SchemaType {
        if ($schema->kind !== SchemaType::REF) {
            return $schema;
        }

        if ($depth >= self::MAX_REF_DEPTH) {
            throw new UnmappableSchemaException($pointer, 'ref cycle exceeded resolution depth.');
        }

        $refName = (string) $schema->ref;
        if (!isset($document->namedSchemas[$refName])) {
            throw new UnmappableSchemaException($pointer, \sprintf("ref '%s' is not defined in components/schemas.", $refName));
        }

        return $this->resolveRef($document, $document->namedSchemas[$refName], $pointer, $depth + 1);
    }

    private function requestBodyPointer(OperationModel $operation): string
    {
        return $this->operationPointer($operation) . '/requestBody/content/application~1json/schema';
    }

    private function responsePointer(OperationModel $operation, ResponseModel $response): string
    {
        return $this->operationPointer($operation) . '/responses/' . $response->status . '/content/application~1json/schema';
    }

    private function operationPointer(OperationModel $operation): string
    {
        $encodedPath = str_replace('/', '~1', $operation->path);

        return '#/paths/' . $encodedPath . '/' . strtolower($operation->method);
    }
}

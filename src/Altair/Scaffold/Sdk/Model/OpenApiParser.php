<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Sdk\Model;

use Altair\Scaffold\Sdk\Exception\SdkException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Parses an OpenAPI 3.1 document (YAML string or decoded array) into the
 * language-neutral {@see OpenApiDocument} model.
 *
 * Uses `symfony/yaml` (already a scaffold dependency) rather than pulling
 * in a full OpenAPI library — the emitters only need the subset the
 * framework's own `OpenApiEmitter` produces, plus `$ref`/enum support for
 * hand-authored documents.
 */
final readonly class OpenApiParser
{
    private const array HTTP_METHODS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    public function parseYaml(string $yaml): OpenApiDocument
    {
        try {
            $decoded = Yaml::parse($yaml);
        } catch (ParseException $parseException) {
            throw new SdkException('Invalid OpenAPI YAML: ' . $parseException->getMessage(), 0, $parseException);
        }

        if (!\is_array($decoded)) {
            throw new SdkException('OpenAPI document must be a YAML map at the top level.');
        }

        return $this->parse($decoded);
    }

    /**
     * @param array<string, mixed> $doc
     */
    public function parse(array $doc): OpenApiDocument
    {
        /** @var array<string, mixed> $info */
        $info = \is_array($doc['info'] ?? null) ? $doc['info'] : [];
        /** @var array<string, mixed> $paths */
        $paths = \is_array($doc['paths'] ?? null) ? $doc['paths'] : [];

        $namedSchemas = $this->parseComponents($doc);
        $operations = [];

        foreach ($paths as $path => $item) {
            if (!\is_array($item)) {
                continue;
            }

            // Path-item-level `parameters` apply to every operation under the path.
            $pathLevelParams = \is_array($item['parameters'] ?? null) ? $item['parameters'] : [];

            foreach ($item as $method => $operation) {
                // Skip non-operation keys (parameters, summary, description, $ref).
                if (!\is_string($method)) {
                    continue;
                }

                if (!\in_array(strtolower($method), self::HTTP_METHODS, true)) {
                    continue;
                }

                if (!\is_array($operation)) {
                    continue;
                }

                $operations[] = $this->parseOperation((string) $path, strtolower($method), $operation, $pathLevelParams);
            }
        }

        return new OpenApiDocument(
            title: (string) ($info['title'] ?? 'API'),
            version: (string) ($info['version'] ?? '0.0.0'),
            operations: $operations,
            namedSchemas: $namedSchemas,
        );
    }

    /**
     * @param array<string, mixed> $doc
     *
     * @return array<string, SchemaType>
     */
    private function parseComponents(array $doc): array
    {
        $components = $doc['components'] ?? null;
        $schemas = \is_array($components) && \is_array($components['schemas'] ?? null) ? $components['schemas'] : [];

        $out = [];
        foreach ($schemas as $name => $schema) {
            if (\is_string($name) && \is_array($schema)) {
                $out[$name] = $this->parseSchema($schema);
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed>    $operation
     * @param array<int|string, mixed> $pathLevelParams
     */
    private function parseOperation(string $path, string $method, array $operation, array $pathLevelParams = []): OperationModel
    {
        $pathParams = $this->extractPathParameters($path);
        $parameters = $this->parseParameters($pathParams, $pathLevelParams, $operation['parameters'] ?? null);

        $requestBody = null;
        $schema = $this->requestBodySchema($operation);
        if ($schema !== null) {
            $requestBody = $this->parseSchema($schema);
        }

        $responses = [];
        /** @var array<string, mixed> $rawResponses */
        $rawResponses = \is_array($operation['responses'] ?? null) ? $operation['responses'] : [];
        foreach ($rawResponses as $status => $response) {
            if (!\is_array($response)) {
                continue;
            }

            $responses[] = $this->parseResponse((string) $status, $response);
        }

        $operationId = isset($operation['operationId']) && \is_string($operation['operationId']) && $operation['operationId'] !== ''
            ? $operation['operationId']
            : $this->synthesizeOperationId($method, $path);

        return new OperationModel(
            operationId: $operationId,
            method: strtoupper($method),
            path: $path,
            pathParameters: $pathParams,
            requestBody: $requestBody,
            responses: $responses,
            summary: isset($operation['summary']) && \is_string($operation['summary']) ? $operation['summary'] : '',
            extensions: $this->extractExtensions($operation),
            parameters: $parameters,
        );
    }

    /**
     * Merges path-template params (as required path strings) with declared
     * path-level then operation-level `parameters`, deduped by `in:name` with
     * the most specific (operation-level) winning. Parameter `$ref`s are not
     * resolved (the {@see CoverageScanner} warns about them).
     *
     * @param  list<string>            $templateParams Path-template `{name}`s.
     * @param  array<int|string,mixed> $pathLevelParams
     * @return list<ParameterModel>
     */
    private function parseParameters(array $templateParams, array $pathLevelParams, mixed $operationParams): array
    {
        /** @var array<string, ParameterModel> $byKey */
        $byKey = [];
        /** @var list<string> $order */
        $order = [];
        $add = static function (ParameterModel $parameter) use (&$byKey, &$order): void {
            $key = $parameter->in . ':' . $parameter->name;
            if (!isset($byKey[$key])) {
                $order[] = $key;
            }

            $byKey[$key] = $parameter;
        };

        foreach ($templateParams as $name) {
            $add(new ParameterModel($name, ParameterModel::IN_PATH, true));
        }

        foreach ($this->toParameterModels($pathLevelParams) as $parameter) {
            $add($parameter);
        }

        foreach ($this->toParameterModels(\is_array($operationParams) ? $operationParams : []) as $parameter) {
            $add($parameter);
        }

        return array_values(array_map(static fn(string $key): ParameterModel => $byKey[$key], $order));
    }

    /**
     * @param  array<int|string, mixed> $raw
     * @return list<ParameterModel>
     */
    private function toParameterModels(array $raw): array
    {
        $out = [];
        foreach ($raw as $param) {
            if (!\is_array($param)) {
                continue;
            }

            if (isset($param['$ref'])) {
                continue;
            }

            $name = $param['name'] ?? null;
            $in = $param['in'] ?? null;
            if (!\is_string($name)) {
                continue;
            }

            if (!\is_string($in)) {
                continue;
            }

            $out[] = new ParameterModel(
                name: $name,
                in: $in,
                required: ($param['required'] ?? false) === true || $in === ParameterModel::IN_PATH,
                schema: \is_array($param['schema'] ?? null) ? $this->parseSchema($param['schema']) : null,
            );
        }

        return $out;
    }

    /**
     * `x-altair-*` keys carried at the operation level are preserved
     * verbatim so the import path can round-trip framework-specific
     * concerns (domain class, persistence, queue) that OpenAPI itself
     * cannot express. Unknown extension keys still ride along so a v2
     * extension can't be stripped by a v1 parser.
     *
     * @param  array<string, mixed> $operation
     * @return array<string, mixed>
     */
    private function extractExtensions(array $operation): array
    {
        $extensions = [];
        foreach ($operation as $key => $value) {
            if (\is_string($key) && str_starts_with($key, 'x-altair-')) {
                $extensions[$key] = $value;
            }
        }

        return $extensions;
    }

    /**
     * @param array<string, mixed> $operation
     *
     * @return array<string, mixed>|null
     */
    private function requestBodySchema(array $operation): ?array
    {
        $requestBody = $operation['requestBody'] ?? null;
        if (!\is_array($requestBody)) {
            return null;
        }

        $content = $requestBody['content'] ?? null;
        if (!\is_array($content)) {
            return null;
        }

        return $this->bodySchemaFromContent($content);
    }

    /**
     * Selects the request-body schema from a `content` map. `application/json`
     * wins when it carries a schema; otherwise the first content type whose
     * schema is an object or array (multipart/form-data, x-www-form-urlencoded)
     * is read, so non-JSON *object* bodies map instead of being dropped. A
     * binary/scalar body (`application/octet-stream`) has no named-field
     * representation, so it yields null and the {@see CoverageScanner} reports
     * it. The chosen schema re-emits as `application/json` on export — the
     * wire content type is normalized, the body structure round-trips.
     *
     * @param  array<string, mixed>      $content
     * @return array<string, mixed>|null
     */
    private function bodySchemaFromContent(array $content): ?array
    {
        $json = $content['application/json'] ?? null;
        if (\is_array($json) && \is_array($json['schema'] ?? null)) {
            return $json['schema'];
        }

        foreach ($content as $type => $media) {
            if ($type === 'application/json') {
                continue;
            }

            if (!\is_array($media)) {
                continue;
            }

            if (!\is_array($media['schema'] ?? null)) {
                continue;
            }

            if ($this->isMappableRootSchema($media['schema'])) {
                return $media['schema'];
            }
        }

        return null;
    }

    /**
     * Whether a raw schema node maps to an Altair input body — an object
     * (named fields), an array (a single named list field), or a `$ref`
     * (resolved downstream). Scalars/binaries are excluded so an
     * `application/octet-stream` body stays unmapped rather than producing a
     * nameless scalar field.
     *
     * @param array<string, mixed> $schema
     */
    private function isMappableRootSchema(array $schema): bool
    {
        if (isset($schema['$ref'])) {
            return true;
        }

        if (\is_array($schema['allOf'] ?? null)) {
            return true;
        }

        if (\is_array($schema['properties'] ?? null)) {
            return true;
        }

        $type = $schema['type'] ?? null;
        if (\is_array($type)) {
            $type = $this->firstNonNull($type);
        }

        return $type === 'object' || $type === 'array';
    }

    /**
     * @param array<string, mixed> $response
     */
    private function parseResponse(string $status, array $response): ResponseModel
    {
        $schema = null;
        $content = $response['content'] ?? null;
        if (\is_array($content)) {
            $schemaArray = $this->responseSchemaFromContent($content);
            if ($schemaArray !== null) {
                $schema = $this->parseSchema($schemaArray);
            }
        }

        return new ResponseModel(
            status: $status,
            schema: $schema,
            description: isset($response['description']) && \is_string($response['description']) ? $response['description'] : '',
        );
    }

    /**
     * Like {@see bodySchemaFromContent} but for responses, which Altair's
     * `output:` block can render from any schema kind (including scalars), so
     * the fallback accepts the first content type carrying a schema rather than
     * restricting to object/array roots.
     *
     * @param  array<string, mixed>      $content
     * @return array<string, mixed>|null
     */
    private function responseSchemaFromContent(array $content): ?array
    {
        $json = $content['application/json'] ?? null;
        if (\is_array($json) && \is_array($json['schema'] ?? null)) {
            return $json['schema'];
        }

        foreach ($content as $type => $media) {
            if ($type === 'application/json') {
                continue;
            }

            if (\is_array($media) && \is_array($media['schema'] ?? null)) {
                return $media['schema'];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function parseSchema(array $schema): SchemaType
    {
        $nullable = ($schema['nullable'] ?? false) === true;

        if (isset($schema['$ref']) && \is_string($schema['$ref'])) {
            return SchemaType::ref($this->refName($schema['$ref']), $nullable);
        }

        if (isset($schema['enum']) && \is_array($schema['enum'])) {
            $values = array_values(array_map(static fn(mixed $v): string => (string) $v, $schema['enum']));

            return SchemaType::enum($values, $nullable);
        }

        if (isset($schema['allOf']) && \is_array($schema['allOf'])) {
            return $this->parseAllOf($schema, $nullable);
        }

        $type = $schema['type'] ?? null;
        // OpenAPI 3.1 allows `type: [string, "null"]`.
        if (\is_array($type)) {
            $nullable = $nullable || \in_array('null', $type, true);
            $type = $this->firstNonNull($type);
        }

        return match ($type) {
            'array' => SchemaType::arrayOf(
                \is_array($schema['items'] ?? null) ? $this->parseSchema($schema['items']) : SchemaType::mixed(),
                $nullable,
            ),
            'object' => SchemaType::object($this->parseProperties($schema), $nullable),
            'integer' => SchemaType::scalar('integer', $this->formatOf($schema), $nullable, $this->constraintsOf($schema)),
            'number' => SchemaType::scalar('number', $this->formatOf($schema), $nullable, $this->constraintsOf($schema)),
            'boolean' => SchemaType::scalar('boolean', null, $nullable),
            'string' => SchemaType::scalar('string', $this->formatOf($schema), $nullable, $this->constraintsOf($schema)),
            default => $this->fallbackSchema($schema, $nullable),
        };
    }

    /**
     * Parses an `allOf` node into an {@see SchemaType::ALLOF} carrying its
     * subschemas (refs kept unresolved). The mapper merges them into one
     * object — Altair has no representation for composition. Sibling keywords
     * on the `allOf` node itself (`properties`/`required`) are a valid extra
     * constraint, so they ride along as an additional inline object subschema.
     *
     * @param array<string, mixed> $schema
     */
    private function parseAllOf(array $schema, bool $nullable): SchemaType
    {
        $subschemas = [];
        foreach ($schema['allOf'] as $sub) {
            if (\is_array($sub)) {
                $subschemas[] = $this->parseSchema($sub);
            }
        }

        if (\is_array($schema['properties'] ?? null)) {
            $subschemas[] = SchemaType::object($this->parseProperties($schema));
        }

        return SchemaType::allOf($subschemas, $nullable);
    }

    /**
     * The JSON-Schema validation keywords the importer maps to Altair rules.
     *
     * @param  array<string, mixed>            $schema
     * @return array<string, int|float|string>
     */
    private function constraintsOf(array $schema): array
    {
        $out = [];
        foreach (['minLength', 'maxLength', 'minimum', 'maximum'] as $keyword) {
            $value = $schema[$keyword] ?? null;
            if (\is_int($value) || \is_float($value)) {
                $out[$keyword] = $value;
            }
        }

        if (isset($schema['pattern']) && \is_string($schema['pattern'])) {
            $out['pattern'] = $schema['pattern'];
        }

        return $out;
    }

    /**
     * A schema with `properties` but no explicit `type` is still an object.
     *
     * @param array<string, mixed> $schema
     */
    private function fallbackSchema(array $schema, bool $nullable): SchemaType
    {
        if (\is_array($schema['properties'] ?? null)) {
            return SchemaType::object($this->parseProperties($schema), $nullable);
        }

        return SchemaType::mixed();
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, array{ schema: SchemaType, required: bool }>
     */
    private function parseProperties(array $schema): array
    {
        $properties = \is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $required = \is_array($schema['required'] ?? null)
            ? array_map(static fn(mixed $v): string => (string) $v, $schema['required'])
            : [];

        $out = [];
        foreach ($properties as $name => $propSchema) {
            if (!\is_string($name)) {
                continue;
            }

            if (!\is_array($propSchema)) {
                continue;
            }

            $out[$name] = [
                'schema' => $this->parseSchema($propSchema),
                'required' => \in_array($name, $required, true),
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function extractPathParameters(string $path): array
    {
        preg_match_all('/\{([A-Za-z_]\w*)\}/', $path, $matches);

        return $matches[1];
    }

    /**
     * Synthesises a camelCase `operationId` when the document omits one:
     * `POST /users` → `createUser` (singularised), `GET /users/{id}` →
     * `getUsersById`, `DELETE /orders/{orderId}` → `deleteOrdersByOrderId`.
     */
    private function synthesizeOperationId(string $method, string $path): string
    {
        $verb = match (strtoupper($method)) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            'GET' => 'get',
            default => strtolower($method),
        };

        $segments = array_values(array_filter(explode('/', $path), static fn(string $s): bool => $s !== ''));
        $last = end($segments) ?: 'resource';

        if (str_starts_with($last, '{')) {
            $parameter = trim($last, '{}');
            $base = $this->lastResourceSegment($segments);

            return $verb . $this->pascalCase($base) . 'By' . $this->pascalCase($parameter);
        }

        // For POST-to-collection the singular reads better: createUser, not createUsers.
        $base = strtoupper($method) === 'POST' ? $this->singularize($last) : $last;

        return $verb . $this->pascalCase($base);
    }

    /**
     * @param list<string> $segments
     */
    private function lastResourceSegment(array $segments): string
    {
        for ($i = \count($segments) - 1; $i >= 0; --$i) {
            if (!str_starts_with($segments[$i], '{')) {
                return $segments[$i];
            }
        }

        return 'resource';
    }

    private function pascalCase(string $value): string
    {
        $words = array_filter(preg_split('/[^a-zA-Z0-9]+/', $value) ?: []);

        return implode('', array_map(ucfirst(...), $words));
    }

    private function singularize(string $value): string
    {
        if (str_ends_with($value, 'ies') && \strlen($value) > 3) {
            return substr($value, 0, -3) . 'y';
        }

        if (str_ends_with($value, 'ses') && \strlen($value) > 3) {
            return substr($value, 0, -2);
        }

        return str_ends_with($value, 's') && !str_ends_with($value, 'ss')
            ? substr($value, 0, -1)
            : $value;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function formatOf(array $schema): ?string
    {
        return isset($schema['format']) && \is_string($schema['format']) ? $schema['format'] : null;
    }

    private function refName(string $ref): string
    {
        $parts = explode('/', $ref);

        return end($parts) ?: $ref;
    }

    /**
     * @param array<mixed> $types
     */
    private function firstNonNull(array $types): ?string
    {
        foreach ($types as $type) {
            if ($type !== 'null' && \is_string($type)) {
                return $type;
            }
        }

        return null;
    }
}

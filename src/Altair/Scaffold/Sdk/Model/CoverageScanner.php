<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Sdk\Model;

/**
 * Walks a raw OpenAPI document and reports every construct `openapi:import`
 * does not map — so the import warns instead of silently dropping.
 *
 * {@see OpenApiParser} reads only a subset (paths, JSON bodies/responses,
 * component schemas), discarding the rest *before* the mapper runs. This
 * scanner inspects the same raw document independently and produces one
 * warning per dropped construct, in document order (deterministic, so import
 * receipts stay byte-stable). It is Phase 1 of #214 — coverage is widened as
 * later phases actually map these features.
 */
final readonly class CoverageScanner
{
    private const array HTTP_METHODS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    /**
     * @param  array<string, mixed> $document Raw decoded OpenAPI document.
     * @return list<string>
     */
    public function scan(array $document): array
    {
        $warnings = [];

        $this->scanDocument($document, $warnings);

        $paths = $document['paths'] ?? null;
        if (\is_array($paths)) {
            foreach ($paths as $path => $item) {
                if (\is_string($path) && \is_array($item)) {
                    $this->scanPathItem($path, $item, $warnings);
                }
            }
        }

        return $warnings;
    }

    /**
     * @param array<string, mixed> $document
     * @param list<string>         $warnings
     */
    private function scanDocument(array $document, array &$warnings): void
    {
        if ($this->nonEmptyArray($document['servers'] ?? null)) {
            $warnings[] = 'document `servers` are not imported.';
        }

        if ($this->nonEmptyArray($document['security'] ?? null)) {
            $warnings[] = 'global `security` requirements are not imported.';
        }

        if ($this->nonEmptyArray($document['webhooks'] ?? null)) {
            $warnings[] = '`webhooks` are not imported (only `paths` are read).';
        }

        $components = $document['components'] ?? null;
        if (\is_array($components) && $this->nonEmptyArray($components['securitySchemes'] ?? null)) {
            $warnings[] = '`components.securitySchemes` are not imported.';
        }
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string>         $warnings
     */
    private function scanPathItem(string $path, array $item, array &$warnings): void
    {
        if (isset($item['$ref'])) {
            $warnings[] = \sprintf('path item `$ref` on %s is not imported.', $path);

            return;
        }

        $this->scanParameters($item['parameters'] ?? null, $path . ' (all operations)', $warnings);

        foreach (self::HTTP_METHODS as $method) {
            $operation = $item[$method] ?? null;
            if (\is_array($operation)) {
                $this->scanOperation(strtoupper($method) . ' ' . $path, $operation, $warnings);
            }
        }
    }

    /**
     * @param array<string, mixed> $operation
     * @param list<string>         $warnings
     */
    private function scanOperation(string $where, array $operation, array &$warnings): void
    {
        $this->scanParameters($operation['parameters'] ?? null, $where, $warnings);
        $this->scanRequestBody($operation['requestBody'] ?? null, $where, $warnings);
        $this->scanResponses($operation['responses'] ?? null, $where, $warnings);

        if ($this->nonEmptyArray($operation['security'] ?? null)) {
            $warnings[] = \sprintf('operation `security` on %s is not imported.', $where);
        }

        if ($this->nonEmptyArray($operation['callbacks'] ?? null)) {
            $warnings[] = \sprintf('`callbacks` on %s are not imported.', $where);
        }
    }

    /**
     * Path/query/header/cookie parameters are imported (as inputs tagged with
     * their `in` location). A parameter `$ref` is not resolved, so it is warned.
     *
     * @param list<string> $warnings
     */
    private function scanParameters(mixed $parameters, string $where, array &$warnings): void
    {
        if (!\is_array($parameters)) {
            return;
        }

        foreach ($parameters as $parameter) {
            if (\is_array($parameter) && isset($parameter['$ref'])) {
                $warnings[] = \sprintf('parameter `$ref` on %s is not imported.', $where);
            }
        }
    }

    /**
     * Mirrors {@see OpenApiParser::bodySchemaFromContent}: `application/json`
     * (with a schema) is read and the other representations dropped; with no
     * JSON, the first content type carrying an object/array schema is read
     * (normalized to the request body), and a binary/scalar-only body has no
     * Altair representation and is dropped.
     *
     * @param list<string> $warnings
     */
    private function scanRequestBody(mixed $requestBody, string $where, array &$warnings): void
    {
        if (!\is_array($requestBody)) {
            return;
        }

        if (isset($requestBody['$ref'])) {
            $warnings[] = \sprintf('requestBody `$ref` on %s is not imported (body dropped).', $where);

            return;
        }

        $content = $requestBody['content'] ?? null;
        if (!\is_array($content)) {
            return;
        }

        if ($this->hasSchema($content['application/json'] ?? null)) {
            $otherTypes = array_values(array_filter(
                array_keys($content),
                static fn(int|string $type): bool => \is_string($type) && $type !== 'application/json',
            ));
            if ($otherTypes !== []) {
                $warnings[] = \sprintf('request body content type(s) %s on %s are not imported (only application/json is read).', implode(', ', $otherTypes), $where);
            }

            return;
        }

        $normalizedFrom = $this->firstMappableContentType($content);
        if ($normalizedFrom !== null) {
            $warnings[] = \sprintf('request body on %s has no application/json; its schema is read from %s (normalized).', $where, $normalizedFrom);

            return;
        }

        $types = array_values(array_filter(array_keys($content), \is_string(...)));
        if ($types !== []) {
            $warnings[] = \sprintf('request body on %s uses %s with no mappable object schema; not imported.', $where, implode(', ', $types));
        }
    }

    /**
     * Mirrors {@see OpenApiParser::responseSchemaFromContent}: a response whose
     * schema comes from a non-JSON content type is read (normalized) rather than
     * dropped, so the importer surfaces the normalization — `output:` blocks
     * always re-emit as `application/json`. When `application/json` carries the
     * schema the other representations are alternative views, not a loss, so
     * they are not warned (unlike a request body, which the client must choose
     * one representation to send).
     *
     * @param list<string> $warnings
     */
    private function scanResponses(mixed $responses, string $where, array &$warnings): void
    {
        if (!\is_array($responses)) {
            return;
        }

        foreach ($responses as $status => $response) {
            if (!\is_array($response)) {
                continue;
            }

            $content = $response['content'] ?? null;
            if (!\is_array($content)) {
                continue;
            }

            if ($this->hasSchema($content['application/json'] ?? null)) {
                continue;
            }

            $normalizedFrom = $this->firstContentTypeWithSchema($content);
            if ($normalizedFrom !== null) {
                $warnings[] = \sprintf('response %s on %s has no application/json; its schema is read from %s (normalized).', (string) $status, $where, $normalizedFrom);
            }
        }
    }

    /**
     * First content type whose schema is an object/array/`$ref` root — the one
     * {@see OpenApiParser} would normalize the body from when no JSON is present.
     *
     * @param array<int|string, mixed> $content
     */
    private function firstMappableContentType(array $content): ?string
    {
        foreach ($content as $type => $media) {
            if ($type === 'application/json') {
                continue;
            }

            if (\is_string($type) && \is_array($media) && $this->isMappableRootSchema($media['schema'] ?? null)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * First non-JSON content type carrying any schema — the response counterpart
     * to {@see firstMappableContentType} (responses map scalar schemas too).
     *
     * @param array<int|string, mixed> $content
     */
    private function firstContentTypeWithSchema(array $content): ?string
    {
        foreach ($content as $type => $media) {
            if ($type === 'application/json') {
                continue;
            }

            if (\is_string($type) && $this->hasSchema($media)) {
                return $type;
            }
        }

        return null;
    }

    private function isMappableRootSchema(mixed $schema): bool
    {
        if (!\is_array($schema)) {
            return false;
        }

        if (isset($schema['$ref'])) {
            return true;
        }

        if (\is_array($schema['properties'] ?? null)) {
            return true;
        }

        $type = $schema['type'] ?? null;
        if (\is_array($type)) {
            $type = $this->firstNonNullType($type);
        }

        return $type === 'object' || $type === 'array';
    }

    /**
     * Mirrors {@see OpenApiParser}'s `firstNonNull`: the first non-`null`,
     * string entry of an OpenAPI 3.1 `type: [..]` union (so `[object, "null"]`
     * resolves to `object`). Identical contract keeps the parser and scanner
     * from disagreeing about which bodies are mappable.
     *
     * @param array<mixed> $types
     */
    private function firstNonNullType(array $types): ?string
    {
        foreach ($types as $type) {
            if ($type !== 'null' && \is_string($type)) {
                return $type;
            }
        }

        return null;
    }

    private function hasSchema(mixed $media): bool
    {
        return \is_array($media) && \is_array($media['schema'] ?? null);
    }

    private function nonEmptyArray(mixed $value): bool
    {
        return \is_array($value) && $value !== [];
    }
}

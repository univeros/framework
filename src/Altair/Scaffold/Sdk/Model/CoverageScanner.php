<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Sdk\Model;

use LogicException;

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
     * Inline-schema recursion ceiling for {@see compositionKeywords} — a hostile, deeply
     * nested document stops being walked rather than exhausting the stack.
     */
    private const int MAX_SCAN_DEPTH = 64;

    /**
     * @param  array<string, mixed> $document Raw decoded OpenAPI document.
     * @return list<string>
     */
    public function scan(array $document): array
    {
        $warnings = [];

        $this->scanDocument($document, $warnings);
        $this->scanComponentSchemas($document, $warnings);

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
     * A named component schema that uses a composition / open-map keyword
     * (anywhere within it) is reported once per keyword, in document order:
     * `allOf` is flattened, `oneOf`/`anyOf` are unions with no Altair
     * representation, and `additionalProperties` is an open-ended map.
     *
     * @param array<string, mixed> $document
     * @param list<string>         $warnings
     */
    private function scanComponentSchemas(array $document, array &$warnings): void
    {
        $components = $document['components'] ?? null;
        $schemas = \is_array($components) && \is_array($components['schemas'] ?? null) ? $components['schemas'] : [];

        foreach ($schemas as $name => $schema) {
            if (\is_string($name) && \is_array($schema)) {
                $this->warnCompositionKeywords($schema, \sprintf('`components.schemas.%s`', $name), $warnings);
            }
        }
    }

    /**
     * Appends one warning per composition / open-map keyword found anywhere in
     * the schema tree, in a fixed keyword order so receipts stay byte-stable.
     *
     * @param array<string, mixed> $schema
     * @param list<string>         $warnings
     */
    private function warnCompositionKeywords(array $schema, string $subject, array &$warnings): void
    {
        foreach ($this->compositionKeywords($schema) as $keyword) {
            $warnings[] = $this->compositionWarning($keyword, $subject);
        }
    }

    private function compositionWarning(string $keyword, string $subject): string
    {
        return match ($keyword) {
            'allOf' => \sprintf('%s uses allOf; its subschemas are merged into one object on import (composition not preserved).', $subject),
            'oneOf' => \sprintf('%s uses oneOf; a union has no Altair representation, so it is not imported.', $subject),
            'anyOf' => \sprintf('%s uses anyOf; a union has no Altair representation, so it is not imported.', $subject),
            'additionalProperties' => \sprintf('%s uses additionalProperties; open-ended map keys are not imported.', $subject),
            // compositionKeywords only ever yields the four keywords above; a new
            // one must add its arm here rather than fall through to a wrong message.
            default => throw new LogicException(\sprintf('unhandled composition keyword: %s', $keyword)),
        };
    }

    /**
     * Recursively collects which composition / open-map keywords
     * (`allOf`, `oneOf`, `anyOf`, `additionalProperties`) appear anywhere within
     * a single inline schema tree — its `properties`, array `items`, and the
     * `allOf`/`oneOf`/`anyOf` subschema lists. `$ref`s are not followed, so this
     * cannot cycle; nesting is bounded by {@see MAX_SCAN_DEPTH}. Returns the
     * present keywords in the canonical order above (deterministic output).
     *
     * @param  array<string, mixed> $schema
     * @return list<string>
     */
    private function compositionKeywords(array $schema, int $depth = 0): array
    {
        if ($depth >= self::MAX_SCAN_DEPTH) {
            return [];
        }

        $found = [];
        foreach (['allOf', 'oneOf', 'anyOf'] as $keyword) {
            $subschemas = $schema[$keyword] ?? null;
            if (\is_array($subschemas)) {
                $found[$keyword] = true;
                foreach ($subschemas as $subschema) {
                    if (\is_array($subschema)) {
                        $this->collectKeywords($subschema, $depth + 1, $found);
                    }
                }
            }
        }

        if ($this->hasOpenAdditionalProperties($schema)) {
            $found['additionalProperties'] = true;
        }

        $additional = $schema['additionalProperties'] ?? null;
        if (\is_array($additional)) {
            $this->collectKeywords($additional, $depth + 1, $found);
        }

        $this->collectFromChildren($schema, $depth, $found);

        return array_values(array_filter(
            ['allOf', 'oneOf', 'anyOf', 'additionalProperties'],
            static fn(string $keyword): bool => isset($found[$keyword]),
        ));
    }

    /**
     * Merges the keywords of a nested schema into the running set.
     *
     * @param array<string, mixed> $schema
     * @param array<string, true>  $found
     */
    private function collectKeywords(array $schema, int $depth, array &$found): void
    {
        foreach ($this->compositionKeywords($schema, $depth) as $keyword) {
            $found[$keyword] = true;
        }
    }

    /**
     * Recurses into a schema's `properties` and array `items`.
     *
     * @param array<string, mixed> $schema
     * @param array<string, true>  $found
     */
    private function collectFromChildren(array $schema, int $depth, array &$found): void
    {
        $properties = $schema['properties'] ?? null;
        if (\is_array($properties)) {
            foreach ($properties as $property) {
                if (\is_array($property)) {
                    $this->collectKeywords($property, $depth + 1, $found);
                }
            }
        }

        $items = $schema['items'] ?? null;
        if (\is_array($items)) {
            $this->collectKeywords($items, $depth + 1, $found);
        }
    }

    /**
     * `additionalProperties` is lossy only when it permits extra keys — `true`
     * or a schema. An explicit `false` (closed object) is the safe default and
     * is not warned.
     *
     * @param array<string, mixed> $schema
     */
    private function hasOpenAdditionalProperties(array $schema): bool
    {
        if (!\array_key_exists('additionalProperties', $schema)) {
            return false;
        }

        $value = $schema['additionalProperties'];

        return $value === true || \is_array($value);
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

            $this->scanComposition($content['application/json'] ?? null, 'request body', $where, $warnings);

            return;
        }

        $normalizedFrom = $this->firstMappableContentType($content);
        if ($normalizedFrom !== null) {
            $warnings[] = \sprintf('request body on %s has no application/json; its schema is read from %s (normalized).', $where, $normalizedFrom);
            $this->scanComposition($content[$normalizedFrom] ?? null, 'request body', $where, $warnings);

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

            $subject = \sprintf('response %s', (string) $status);

            if ($this->hasSchema($content['application/json'] ?? null)) {
                $this->scanComposition($content['application/json'] ?? null, $subject, $where, $warnings);

                continue;
            }

            $normalizedFrom = $this->firstContentTypeWithSchema($content);
            if ($normalizedFrom !== null) {
                $warnings[] = \sprintf('%s on %s has no application/json; its schema is read from %s (normalized).', $subject, $where, $normalizedFrom);
                $this->scanComposition($content[$normalizedFrom] ?? null, $subject, $where, $warnings);
            }
        }
    }

    /**
     * Warns when the schema actually read for a body/response is itself an
     * inline `allOf` (flattened to a merged object on import). A `$ref` body's
     * composition lives in the named component and is reported by
     * {@see scanComponentSchemas}, so only inline composition surfaces here.
     *
     * @param list<string> $warnings
     */
    private function scanComposition(mixed $media, string $subject, string $where, array &$warnings): void
    {
        $schema = $this->schemaOf($media);
        if ($schema !== null) {
            $this->warnCompositionKeywords($schema, \sprintf('%s on %s', $subject, $where), $warnings);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function schemaOf(mixed $media): ?array
    {
        if (\is_array($media) && \is_array($media['schema'] ?? null)) {
            return $media['schema'];
        }

        return null;
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

        if (\is_array($schema['allOf'] ?? null)) {
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

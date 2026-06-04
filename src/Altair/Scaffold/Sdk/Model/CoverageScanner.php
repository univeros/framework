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

        if ($this->nonEmptyArray($operation['security'] ?? null)) {
            $warnings[] = \sprintf('operation `security` on %s is not imported.', $where);
        }

        if ($this->nonEmptyArray($operation['callbacks'] ?? null)) {
            $warnings[] = \sprintf('`callbacks` on %s are not imported.', $where);
        }
    }

    /**
     * Path parameters are mapped (as strings); query/header/cookie parameters
     * and `$ref` parameters are dropped, so each is warned.
     *
     * @param list<string> $warnings
     */
    private function scanParameters(mixed $parameters, string $where, array &$warnings): void
    {
        if (!\is_array($parameters)) {
            return;
        }

        foreach ($parameters as $parameter) {
            if (!\is_array($parameter)) {
                continue;
            }

            if (isset($parameter['$ref'])) {
                $warnings[] = \sprintf('parameter `$ref` on %s is not imported.', $where);

                continue;
            }

            $in = $parameter['in'] ?? null;
            if (\in_array($in, ['query', 'header', 'cookie'], true)) {
                $name = isset($parameter['name']) && \is_string($parameter['name']) ? $parameter['name'] : '?';
                $warnings[] = \sprintf('%s parameter `%s` on %s is dropped.', $in, $name, $where);
            }
        }
    }

    /**
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

        $otherTypes = array_values(array_filter(
            array_keys($content),
            static fn(int|string $type): bool => \is_string($type) && $type !== 'application/json',
        ));
        if ($otherTypes === []) {
            return;
        }

        $warnings[] = isset($content['application/json'])
            ? \sprintf('request body content type(s) %s on %s are not imported (only application/json is read).', implode(', ', $otherTypes), $where)
            : \sprintf('request body on %s uses %s; only application/json is imported, so the body is dropped.', $where, implode(', ', $otherTypes));
    }

    private function nonEmptyArray(mixed $value): bool
    {
        return \is_array($value) && $value !== [];
    }
}

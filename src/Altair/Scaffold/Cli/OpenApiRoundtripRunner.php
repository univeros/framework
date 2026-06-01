<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Cli;

use Altair\Scaffold\Emitter\OpenApiEmitter;
use Altair\Scaffold\Sdk\Model\OpenApiParser;
use Altair\Scaffold\Spec\Emitter\EmittedSpec;
use Altair\Scaffold\Spec\Emitter\Emitter as SpecEmitter;
use Altair\Scaffold\Spec\Emitter\Exception\UnmappableSchemaException;
use Altair\Scaffold\Spec\Parser as SpecParser;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * In-memory drift gate for `OpenAPI → Altair YAML → OpenAPI`.
 *
 * Two arms run end to end without touching the filesystem:
 *
 * 1. Parse the source OpenAPI document.
 * 2. Emit Altair YAML specs from it (#161 emitter).
 * 3. Parse each spec back through {@see SpecParser}.
 * 4. Re-emit each as an OpenAPI fragment (the forward
 *    {@see OpenApiEmitter}).
 * 5. Project both the original document and the re-emitted fragments
 *    into a comparison view of (path, method) tuples — summary,
 *    `x-altair-*` blocks, response status set — and diff the views.
 *
 * Skipped on purpose: `info` and doc-level `tags` drift trivially;
 * `components/schemas` references resolve to inlined object types in
 * the spec and re-emit as inlined objects rather than `$ref`s.
 * Schema-level drift detection lands when the import path gains
 * `parameters[]` + `components/schemas` preservation; this gate's job
 * is to keep operation coverage and Altair-extension preservation
 * honest in the meantime.
 */
final readonly class OpenApiRoundtripRunner
{
    public function __construct(
        private OpenApiParser $openApiParser = new OpenApiParser(),
        private SpecEmitter $specEmitter = new SpecEmitter(),
        private SpecParser $specParser = new SpecParser(),
        private OpenApiEmitter $openApiEmitter = new OpenApiEmitter(),
    ) {}

    public function run(OpenApiRoundtripOptions $options): RoundtripReceipt
    {
        if (!is_file($options->documentPath)) {
            return new RoundtripReceipt(
                clean: false,
                input: $options->documentPath,
                operationsCompared: 0,
                differences: [],
                error: \sprintf("OpenAPI document '%s' is not readable.", $options->documentPath),
            );
        }

        $sourceYaml = (string) @file_get_contents($options->documentPath);

        try {
            $source = $this->openApiParser->parseYaml($sourceYaml);
            $emittedSpecs = $this->specEmitter->emit($source);
            $rebuilt = $this->reemit($emittedSpecs);
            $sourceProjection = $this->projectFromDocument(Yaml::parse($sourceYaml) ?: []);
            $rebuiltProjection = $this->projectFromDocument($rebuilt);
            $differences = $this->compare($sourceProjection, $rebuiltProjection);
        } catch (UnmappableSchemaException $unmappableSchemaException) {
            return new RoundtripReceipt(
                clean: false,
                input: $options->documentPath,
                operationsCompared: 0,
                differences: [],
                error: 'Unmappable schema during round-trip: ' . $unmappableSchemaException->getMessage(),
            );
        } catch (Throwable $throwable) {
            return new RoundtripReceipt(
                clean: false,
                input: $options->documentPath,
                operationsCompared: 0,
                differences: [],
                error: 'Round-trip failed: ' . $throwable->getMessage(),
            );
        }

        return new RoundtripReceipt(
            clean: $differences === [],
            input: $options->documentPath,
            operationsCompared: \count($sourceProjection),
            differences: $differences,
            error: null,
        );
    }

    /**
     * Re-merge the per-spec OpenAPI fragments back into a single document
     * shape so the comparator can walk it the same way it walks the
     * source.
     *
     * @param list<EmittedSpec> $specs
     * @return array<string, mixed>
     */
    private function reemit(array $specs): array
    {
        $merged = ['openapi' => '3.1.0', 'info' => ['title' => 'roundtrip', 'version' => '0.0.0'], 'paths' => []];

        foreach ($specs as $spec) {
            $specAst = $this->specParser->parseString($spec->contents, $spec->relativePath);
            $fragment = $this->openApiEmitter->emit($specAst);
            /** @var array<string, mixed> $fragmentArray */
            $fragmentArray = Yaml::parse($fragment->contents) ?: [];

            /** @var array<string, mixed> $paths */
            $paths = \is_array($fragmentArray['paths'] ?? null) ? $fragmentArray['paths'] : [];
            foreach ($paths as $path => $operations) {
                if (!\is_array($operations)) {
                    continue;
                }

                $merged['paths'][$path] = array_merge(
                    $merged['paths'][$path] ?? [],
                    $operations,
                );
            }
        }

        return $merged;
    }

    /**
     * Reduces a parsed OpenAPI document to the subset this gate compares.
     * Hides drift in fields the round-trip cannot preserve today
     * (info / components / doc-level tags / schemas) so the gate only
     * fails on differences that matter.
     *
     * @param  array<string, mixed> $document
     * @return array<string, array<string, mixed>>
     */
    private function projectFromDocument(array $document): array
    {
        $projection = [];
        $paths = \is_array($document['paths'] ?? null) ? $document['paths'] : [];

        foreach ($paths as $path => $methods) {
            if (!\is_array($methods)) {
                continue;
            }

            if (!\is_string($path)) {
                continue;
            }

            foreach ($methods as $method => $operation) {
                if (!\is_string($method)) {
                    continue;
                }

                if (!\is_array($operation)) {
                    continue;
                }

                $key = strtoupper($method) . ' ' . $path;
                $projection[$key] = [
                    'summary' => isset($operation['summary']) && \is_string($operation['summary']) ? $operation['summary'] : '',
                    'x-altair-domain' => $operation['x-altair-domain'] ?? null,
                    'x-altair-persistence' => $operation['x-altair-persistence'] ?? null,
                    'x-altair-queue' => $operation['x-altair-queue'] ?? null,
                    'x-altair-idempotency' => $operation['x-altair-idempotency'] ?? null,
                    'x-altair-webhook' => $operation['x-altair-webhook'] ?? null,
                    'response_statuses_with_schema' => $this->responseStatusesWithSchema($operation),
                ];
            }
        }

        ksort($projection);

        return $projection;
    }

    /**
     * Only statuses that carry an `application/json` schema in the source
     * survive the round trip — Altair's `output:` block has no way to
     * represent a description-only response (e.g. `404`, `204 No Content`).
     * Comparing only schema-bearing statuses keeps the gate focused on
     * losses the framework can actually fix.
     *
     * @param  array<string, mixed> $operation
     * @return list<string>
     */
    private function responseStatusesWithSchema(array $operation): array
    {
        $responses = \is_array($operation['responses'] ?? null) ? $operation['responses'] : [];
        $statuses = [];
        foreach ($responses as $status => $response) {
            if (!\is_array($response)) {
                continue;
            }

            $content = $response['content'] ?? null;
            if (!\is_array($content)) {
                continue;
            }

            if (!\is_array($content['application/json'] ?? null)) {
                continue;
            }

            $statuses[] = (string) $status;
        }

        sort($statuses);

        return $statuses;
    }

    /**
     * @param  array<string, array<string, mixed>>   $expected
     * @param  array<string, array<string, mixed>>   $actual
     * @return list<RoundtripDifference>
     */
    private function compare(array $expected, array $actual): array
    {
        $differences = [];

        foreach ($expected as $key => $expectedOp) {
            $pointer = $this->keyPointer($key);
            if (!isset($actual[$key])) {
                $differences[] = new RoundtripDifference(
                    kind: RoundtripDifference::KIND_MISSING_OPERATION,
                    pointer: $pointer,
                    expected: $expectedOp,
                    actual: null,
                    message: \sprintf("operation '%s' is in the source but missing from the round-tripped output.", $key),
                );
                continue;
            }

            $actualOp = $actual[$key];

            if ($expectedOp['summary'] !== $actualOp['summary']) {
                $differences[] = new RoundtripDifference(
                    kind: RoundtripDifference::KIND_SUMMARY_DRIFT,
                    pointer: $pointer . '/summary',
                    expected: $expectedOp['summary'],
                    actual: $actualOp['summary'],
                    message: 'summary text changed during round-trip.',
                );
            }

            foreach (['x-altair-domain', 'x-altair-persistence', 'x-altair-queue', 'x-altair-idempotency', 'x-altair-webhook'] as $extension) {
                // Tolerate enrichment: drift only fires when the source carried the
                // extension and the round-trip changed or dropped it. A source
                // doc without x-altair-domain that gets a synthesised one back
                // is the importer doing its job, not a regression.
                if ($expectedOp[$extension] !== null && $expectedOp[$extension] !== $actualOp[$extension]) {
                    $differences[] = new RoundtripDifference(
                        kind: RoundtripDifference::KIND_EXTENSION_DRIFT,
                        pointer: $pointer . '/' . $extension,
                        expected: $expectedOp[$extension],
                        actual: $actualOp[$extension],
                        message: \sprintf("'%s' present in source was lost or changed by the round-trip.", $extension),
                    );
                }
            }

            // Source statuses must be present on the actual side; extras (e.g. the
            // synthesised "default" response when a spec has no outputs) are not drift.
            $missingStatuses = array_values(array_diff($expectedOp['response_statuses_with_schema'], $actualOp['response_statuses_with_schema']));
            if ($missingStatuses !== []) {
                $differences[] = new RoundtripDifference(
                    kind: RoundtripDifference::KIND_STATUS_DRIFT,
                    pointer: $pointer . '/responses',
                    expected: $expectedOp['response_statuses_with_schema'],
                    actual: $actualOp['response_statuses_with_schema'],
                    message: \sprintf('response status(es) %s were dropped during round-trip.', implode(', ', $missingStatuses)),
                );
            }
        }

        foreach ($actual as $key => $actualOp) {
            if (!isset($expected[$key])) {
                $differences[] = new RoundtripDifference(
                    kind: RoundtripDifference::KIND_EXTRA_OPERATION,
                    pointer: $this->keyPointer($key),
                    expected: null,
                    actual: $actualOp,
                    message: \sprintf("operation '%s' was emitted by the round-trip but is not in the source.", $key),
                );
            }
        }

        return $differences;
    }

    private function keyPointer(string $key): string
    {
        // `POST /users` -> `#/paths/~1users/post`
        [$method, $path] = explode(' ', $key, 2) + ['', ''];
        $encodedPath = str_replace('/', '~1', $path);

        return '#/paths/' . $encodedPath . '/' . strtolower($method);
    }
}

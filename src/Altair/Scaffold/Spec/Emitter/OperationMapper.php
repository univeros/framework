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

/**
 * Builds the array structure that, when YAML-dumped, produces a single
 * Altair endpoint spec. Combines the {@see PathDeriver} (endpoint + domain)
 * and {@see SchemaMapper} (input + output) into one tree.
 */
final readonly class OperationMapper
{
    public function __construct(
        private PathDeriver $pathDeriver = new PathDeriver(),
        private SchemaMapper $schemaMapper = new SchemaMapper(),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function map(OpenApiDocument $document, OperationModel $operation): array
    {
        $spec = [
            'endpoint' => $this->endpoint($operation),
        ];

        $inputs = $this->schemaMapper->inputFields($document, $operation);
        if ($inputs !== []) {
            $spec['input'] = $this->inputBlock($inputs);
        }

        $outputs = $this->schemaMapper->outputs($document, $operation);
        if ($outputs !== []) {
            $spec['output'] = $this->outputBlock($outputs);
        }

        $spec['domain'] = $this->resolveDomain($operation);

        $persistence = $this->extensionMap($operation, 'x-altair-persistence');
        if ($persistence !== null) {
            $spec['persistence'] = $persistence;
        }

        $queue = $this->extensionMap($operation, 'x-altair-queue');
        if ($queue !== null) {
            $spec['queue'] = $this->renderQueue($queue);
        }

        $idempotency = $this->idempotencyFromExtension($operation);
        if ($idempotency !== null) {
            $spec['idempotency'] = $idempotency;
        }

        return $spec;
    }

    /**
     * `x-altair-idempotency` carries `ttl` (required) and `scope`
     * (defaults to `tenant`). `mode` is not part of the wire contract
     * — it's a server-side enforcement concern — so the importer
     * defaults it to `optional`, the same default the spec block uses
     * when omitted.
     *
     * @return ?array{ttl: string, scope: string, mode: string}
     */
    private function idempotencyFromExtension(OperationModel $operation): ?array
    {
        $extension = $operation->extensions['x-altair-idempotency'] ?? null;
        if (!\is_array($extension) || !isset($extension['ttl']) || !\is_string($extension['ttl']) || $extension['ttl'] === '') {
            return null;
        }

        return [
            'ttl' => $extension['ttl'],
            'scope' => isset($extension['scope']) && \is_string($extension['scope']) && $extension['scope'] !== ''
                ? $extension['scope']
                : 'tenant',
            'mode' => 'optional',
        ];
    }

    /**
     * Pulls `x-altair-domain` when present so an imported endpoint keeps
     * the FQCN its original spec carried. Falls back to {@see PathDeriver}
     * when the extension is absent.
     *
     * @return array<string, string>
     */
    private function resolveDomain(OperationModel $operation): array
    {
        $extension = $operation->extensions['x-altair-domain'] ?? null;
        if (\is_array($extension) && isset($extension['class']) && \is_string($extension['class']) && $extension['class'] !== '') {
            $invocation = isset($extension['invocation']) && \is_string($extension['invocation']) && $extension['invocation'] !== ''
                ? $extension['invocation']
                : '__invoke';

            return ['class' => $extension['class'], 'invocation' => $invocation];
        }

        return [
            'class' => $this->pathDeriver->domainFqcn($operation),
            'invocation' => '__invoke',
        ];
    }

    /**
     * @return ?array<int|string, mixed>
     */
    private function extensionMap(OperationModel $operation, string $key): ?array
    {
        $value = $operation->extensions[$key] ?? null;

        return \is_array($value) ? $value : null;
    }

    /**
     * Turns the list form `x-altair-queue: [{name, message, ...}]` carried
     * in the OpenAPI extension back into the map form
     * `queue: { name: { message, ... } }` the Altair Parser expects.
     *
     * @param  array<int|string, mixed>      $value
     * @return array<string, array<string, mixed>>
     */
    private function renderQueue(array $value): array
    {
        $result = [];
        foreach ($value as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $name = isset($entry['name']) && \is_string($entry['name']) ? $entry['name'] : null;
            if ($name === null) {
                continue;
            }

            $rendered = $entry;
            unset($rendered['name']);
            $result[$name] = $rendered;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function endpoint(OperationModel $operation): array
    {
        return [
            'method' => strtoupper($operation->method),
            'path' => $operation->path,
            'summary' => $operation->summary,
            'tags' => [$this->pathDeriver->resourceDir($operation)],
        ];
    }

    /**
     * Render the field list as a map keyed by name so the YAML reads naturally
     * and the Parser's "input is a map" expectation is met.
     *
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, array<string, mixed>>
     */
    private function inputBlock(array $fields): array
    {
        $block = [];
        foreach ($fields as $field) {
            $name = (string) $field['name'];
            unset($field['name']);
            $block[$name] = $field;
        }

        return $block;
    }

    /**
     * @param  array<int, array<string, string>>      $outputs
     * @return array<int, array<string, mixed>>
     */
    private function outputBlock(array $outputs): array
    {
        $block = [];
        foreach ($outputs as $status => $body) {
            $block[$status] = ['body' => $body];
        }

        return $block;
    }
}

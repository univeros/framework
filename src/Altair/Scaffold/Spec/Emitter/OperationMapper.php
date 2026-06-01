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

        $webhook = $this->webhookFromExtension($operation);
        if ($webhook !== null) {
            $spec['webhook'] = $webhook;
        }

        return $spec;
    }

    /**
     * `x-altair-webhook` requires `direction` + `signing`; the remaining
     * keys are optional and copied through verbatim when present. Absent
     * keys stay absent so the spec Parser re-applies the WebhookSpec
     * defaults, which is what keeps the round-trip byte-stable: the forward
     * emitter only writes a field when it differs from its default, so a
     * key reaching the importer is always a meaningful (non-default) value.
     *
     * @return ?array<string, mixed>
     */
    private function webhookFromExtension(OperationModel $operation): ?array
    {
        $extension = $operation->extensions['x-altair-webhook'] ?? null;
        if (!\is_array($extension)
            || !isset($extension['direction'], $extension['signing'])
            || !\is_string($extension['direction']) || $extension['direction'] === ''
            || !\is_string($extension['signing']) || $extension['signing'] === '') {
            return null;
        }

        $webhook = [
            'direction' => $extension['direction'],
            'signing' => $extension['signing'],
        ];

        foreach (['secret_name', 'header', 'timestamp_header', 'event_id_header', 'dedupe_ttl', 'timestamp_window', 'dead_letter'] as $key) {
            if (isset($extension[$key]) && \is_string($extension[$key]) && $extension[$key] !== '') {
                $webhook[$key] = $extension[$key];
            }
        }

        $retry = $this->webhookRetryFromExtension($extension['retry'] ?? null);
        if ($retry !== []) {
            $webhook['retry'] = $retry;
        }

        return $webhook;
    }

    /**
     * @return array<string, mixed>
     */
    private function webhookRetryFromExtension(mixed $retry): array
    {
        if (!\is_array($retry)) {
            return [];
        }

        $result = [];
        if (isset($retry['max_attempts']) && \is_int($retry['max_attempts'])) {
            $result['max_attempts'] = $retry['max_attempts'];
        }

        foreach (['backoff', 'base_delay'] as $key) {
            if (isset($retry[$key]) && \is_string($retry[$key]) && $retry[$key] !== '') {
                $result[$key] = $retry[$key];
            }
        }

        return $result;
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

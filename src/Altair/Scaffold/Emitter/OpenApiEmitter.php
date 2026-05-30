<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Emitter;

use Altair\Scaffold\Spec\Ast\OutputResponseSpec;
use Altair\Scaffold\Spec\Ast\PersistenceFieldSpec;
use Altair\Scaffold\Spec\Ast\PersistenceSpec;
use Altair\Scaffold\Spec\Ast\QueueDispatchSpec;
use Altair\Scaffold\Spec\Ast\Spec;
use Symfony\Component\Yaml\Yaml;

/**
 * Emits an OpenAPI 3.1 fragment for a single endpoint.
 *
 * Fragments live in `docs/openapi/<slug>.yaml` and are merged at runtime by
 * EmitOpenApiCommand into a single document.
 */
class OpenApiEmitter
{
    public function __construct(
        private readonly Naming $naming = new Naming(),
        private readonly TypeMapper $typeMapper = new TypeMapper(),
    ) {}

    public function emit(Spec $spec): EmittedFile
    {
        $document = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => $spec->endpoint->summary !== '' ? $spec->endpoint->summary : 'Generated endpoint',
                'version' => '0.0.0',
            ],
            'paths' => [
                $spec->endpoint->path => [
                    strtolower($spec->endpoint->method) => $this->renderOperation($spec),
                ],
            ],
        ];

        $contents = Yaml::dump($document, 8, 2, Yaml::DUMP_OBJECT_AS_MAP);

        return new EmittedFile(
            relativePath: $this->naming->openApiPath($spec),
            contents: $contents,
            kind: EmittedFileKind::OpenApi,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function renderOperation(Spec $spec): array
    {
        $operation = [
            'summary' => $spec->endpoint->summary,
            'tags' => $spec->endpoint->tags,
        ];

        foreach ($this->renderAltairExtensions($spec) as $key => $value) {
            $operation[$key] = $value;
        }

        if ($spec->inputs !== []) {
            $properties = [];
            $required = [];
            foreach ($spec->inputs as $field) {
                $properties[$field->name] = $this->typeMapper->toOpenApiSchema($field);
                if ($this->typeMapper->isRequired($field)) {
                    $required[] = $field->name;
                }
            }

            $schema = ['type' => 'object', 'properties' => $properties];
            if ($required !== []) {
                $schema['required'] = $required;
            }

            $operation['requestBody'] = [
                'required' => $required !== [],
                'content' => [
                    'application/json' => ['schema' => $schema],
                ],
            ];
        }

        $operation['responses'] = $this->renderResponses($spec->outputs);

        return $operation;
    }

    /**
     * Keys are HTTP status codes (or the literal `default`). PHP coerces the
     * numeric-string status keys back to integers, so the honest key type is
     * `int|string`; the emitted YAML renders both forms identically.
     *
     * @param  list<OutputResponseSpec>  $outputs
     * @return array<int|string, mixed>
     */
    private function renderResponses(array $outputs): array
    {
        if ($outputs === []) {
            return ['default' => ['description' => 'Generated default response']];
        }

        $responses = [];
        foreach ($outputs as $output) {
            $responses[(string) $output->status] = [
                'description' => \sprintf('Response %d', $output->status),
                'content' => [
                    'application/json' => [
                        'schema' => $this->renderBodySchema($output->body),
                    ],
                ],
            ];
        }

        return $responses;
    }

    /**
     * @param  array<string, string> $body
     * @return array<string, mixed>
     */
    private function renderBodySchema(array $body): array
    {
        if ($body === []) {
            return ['type' => 'object'];
        }

        $properties = [];
        foreach ($body as $name => $type) {
            $properties[$name] = $this->inferSchema($type);
        }

        return ['type' => 'object', 'properties' => $properties];
    }

    /**
     * Round-trippable `x-altair-*` blocks carrying spec fields OpenAPI 3.1
     * cannot natively express. Lets `openapi:import` recover the original
     * `domain:`, `persistence:`, and `queue:` blocks byte-for-byte instead
     * of having to re-infer them from the path + response shape.
     *
     * @return array<string, mixed>
     */
    private function renderAltairExtensions(Spec $spec): array
    {
        $extensions = [
            'x-altair-domain' => [
                'class' => $spec->domain->class,
                'invocation' => $spec->domain->invocation,
            ],
        ];

        if ($spec->persistence instanceof PersistenceSpec) {
            $extensions['x-altair-persistence'] = $this->renderPersistence($spec->persistence);
        }

        if ($spec->queue !== []) {
            $extensions['x-altair-queue'] = array_values(array_map(
                $this->renderQueueDispatch(...),
                $spec->queue,
            ));
        }

        return $extensions;
    }

    /**
     * @return array<string, mixed>
     */
    private function renderPersistence(PersistenceSpec $persistence): array
    {
        $fields = [];
        foreach ($persistence->entity->fields as $field) {
            $fields[$field->name] = $this->renderPersistenceField($field);
        }

        $block = [
            'entity' => [
                'class' => $persistence->entity->class,
                'table' => $persistence->entity->table,
                'fields' => $fields,
            ],
        ];

        if ($persistence->repository !== '') {
            $block['repository'] = $persistence->repository;
        }

        return $block;
    }

    /**
     * @return array<string, mixed>
     */
    private function renderPersistenceField(PersistenceFieldSpec $field): array
    {
        $rendered = ['type' => $field->type];

        if ($field->primary) {
            $rendered['primary'] = true;
        }

        if ($field->nullable) {
            $rendered['nullable'] = true;
        }

        if ($field->unique) {
            $rendered['unique'] = true;
        }

        if ($field->hasDefault) {
            $rendered['default'] = $field->default;
        }

        if ($field->of !== null) {
            $rendered['of'] = $field->of;
        }

        return $rendered;
    }

    /**
     * @return array<string, mixed>
     */
    private function renderQueueDispatch(QueueDispatchSpec $dispatch): array
    {
        $rendered = [
            'name' => $dispatch->name,
            'message' => $dispatch->message,
            'fields' => $dispatch->fields,
        ];

        if ($dispatch->transport !== null) {
            $rendered['transport'] = $dispatch->transport;
        }

        return $rendered;
    }

    /**
     * @return array<string, mixed>
     */
    private function inferSchema(string $type): array
    {
        $normalized = strtolower(trim($type));

        if (str_starts_with($normalized, 'array<') || str_starts_with($normalized, 'list<')) {
            return ['type' => 'array', 'items' => ['type' => 'string']];
        }

        return match ($normalized) {
            'int', 'integer' => ['type' => 'integer'],
            'float', 'number' => ['type' => 'number'],
            'bool', 'boolean' => ['type' => 'boolean'],
            'string' => ['type' => 'string'],
            default  => ['type' => 'object'],
        };
    }
}

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

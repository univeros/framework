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

        $spec['domain'] = [
            'class' => $this->pathDeriver->domainFqcn($operation),
            'invocation' => '__invoke',
        ];

        return $spec;
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

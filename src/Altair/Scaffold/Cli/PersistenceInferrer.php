<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Cli;

use Altair\Scaffold\Sdk\Model\OperationModel;
use Altair\Scaffold\Spec\Emitter\PathDeriver;

/**
 * Decides whether and how to inject a `persistence:` block into an imported
 * operation's spec structure.
 *
 * V1 rule: only the POST-to-collection (no path parameters) endpoint of a
 * resource carries the `persistence:` block — that is the operation
 * conceptually responsible for creating the entity. Other operations on the
 * same resource (GET/PUT/DELETE on item) reference the entity via response
 * body field types but do not redefine it, mirroring how the existing
 * hand-authored scaffolder works.
 */
final readonly class PersistenceInferrer
{
    public function __construct(
        private string $appNamespace = 'App',
        private PathDeriver $pathDeriver = new PathDeriver(),
    ) {}

    public function shouldApply(OperationModel $operation): bool
    {
        return strtoupper($operation->method) === 'POST' && $operation->pathParameters === [];
    }

    /**
     * @param  array<string, mixed>  $spec     Spec structure produced by {@see \Altair\Scaffold\Spec\Emitter\OperationMapper}.
     * @return array<string, mixed>           Same structure with a `persistence:` block injected when applicable.
     */
    public function apply(OperationModel $operation, array $spec): array
    {
        if (!$this->shouldApply($operation)) {
            return $spec;
        }

        $resourceSingular = $this->pathDeriver->resourceSingular($operation);
        $resourceDir = $this->pathDeriver->resourceDir($operation);
        $entityName = ucfirst($resourceSingular);
        $entityFqcn = $this->appNamespace . '\\' . $entityName . '\\' . $entityName;
        $repositoryFqcn = $this->appNamespace . '\\' . $entityName . '\\' . $entityName . 'Repository';

        $inputBlock = $spec['input'] ?? [];
        $fields = ['id' => ['type' => 'uuid', 'primary' => true]];
        if (\is_array($inputBlock)) {
            foreach ($inputBlock as $name => $field) {
                if (!\is_string($name)) {
                    continue;
                }

                if (!\is_array($field)) {
                    continue;
                }

                $type = $this->mapType((string) ($field['type'] ?? 'string'));
                if ($type === null) {
                    continue;
                }

                $fields[$name] = ['type' => $type];
            }
        }

        $spec['persistence'] = [
            'entity' => [
                'class' => $entityFqcn,
                'table' => $resourceDir,
                'fields' => $fields,
            ],
            'repository' => $repositoryFqcn,
        ];

        return $spec;
    }

    private function mapType(string $inputType): ?string
    {
        return match ($inputType) {
            'string' => 'string',
            'int' => 'int',
            'float' => 'float',
            'bool' => 'bool',
            'array' => 'json',
            default => null,
        };
    }
}

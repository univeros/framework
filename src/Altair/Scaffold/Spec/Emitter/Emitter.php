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
use Altair\Scaffold\Spec\Emitter\Exception\UnmappableSchemaException;
use Symfony\Component\Yaml\Yaml;

/**
 * Walks an {@see OpenApiDocument} and produces one {@see EmittedSpec} per
 * operation. The reverse of `spec:emit-openapi` — turns a parsed OpenAPI 3.1
 * document into a directory of Altair YAML specs.
 *
 * Output is deterministic for the same input (alphabetical key ordering
 * inside each operation, stable filename derivation) so it is golden-file
 * safe.
 */
final readonly class Emitter
{
    private const int YAML_INLINE_LEVEL = 6;

    private const int YAML_INDENT = 2;

    public function __construct(
        private OperationMapper $operationMapper = new OperationMapper(),
        private PathDeriver $pathDeriver = new PathDeriver(),
    ) {}

    /**
     *
     * @throws UnmappableSchemaException When an operation carries a schema the mapper cannot express.
     * @return list<EmittedSpec>
     */
    public function emit(OpenApiDocument $document): array
    {
        $emitted = [];
        $seen = [];

        foreach ($document->operations as $operation) {
            $filename = $this->pathDeriver->filename($operation);

            if (isset($seen[$filename])) {
                throw new UnmappableSchemaException(
                    $this->operationPointer($operation),
                    \sprintf(
                        "filename collision: '%s' is also emitted by '%s %s'. Set distinct operationIds to disambiguate.",
                        $filename,
                        $seen[$filename]['method'],
                        $seen[$filename]['path'],
                    ),
                );
            }

            $structure = $this->operationMapper->map($document, $operation);
            $contents = Yaml::dump(
                $structure,
                self::YAML_INLINE_LEVEL,
                self::YAML_INDENT,
                Yaml::DUMP_OBJECT_AS_MAP,
            );

            $emitted[] = new EmittedSpec(relativePath: $filename, contents: $contents);
            $seen[$filename] = ['method' => $operation->method, 'path' => $operation->path];
        }

        return $emitted;
    }

    private function operationPointer(OperationModel $operation): string
    {
        $encodedPath = str_replace('/', '~1', $operation->path);

        return '#/paths/' . $encodedPath . '/' . strtolower($operation->method);
    }
}

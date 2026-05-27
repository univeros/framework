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
 * Parsed, language-neutral view of a whole OpenAPI 3.1 document.
 *
 * `namedSchemas` holds `components/schemas` entries (emitted as reusable
 * named types); `operations` is the flat list every emitter iterates.
 */
final readonly class OpenApiDocument
{
    /**
     * @param list<OperationModel>        $operations
     * @param array<string, SchemaType>   $namedSchemas Component name → schema.
     */
    public function __construct(
        public string $title,
        public string $version,
        public array $operations,
        public array $namedSchemas = [],
    ) {}
}

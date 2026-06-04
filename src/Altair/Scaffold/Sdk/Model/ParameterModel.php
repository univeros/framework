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
 * One OpenAPI operation parameter — `in` is its location (`path`, `query`,
 * `header`, or `cookie`). Carried on {@see OperationModel} so the importer can
 * turn every parameter into an Altair input tagged with its location (rather
 * than dropping query/header/cookie parameters as earlier releases did).
 */
final readonly class ParameterModel
{
    public const string IN_PATH = 'path';

    public const string IN_QUERY = 'query';

    public const string IN_HEADER = 'header';

    public const string IN_COOKIE = 'cookie';

    public function __construct(
        public string $name,
        public string $in,
        public bool $required,
        public ?SchemaType $schema = null,
    ) {}
}

<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Cli;

/**
 * Resolved options for one `openapi:roundtrip` invocation.
 */
final readonly class OpenApiRoundtripOptions
{
    public function __construct(
        public string $documentPath,
        public bool $check = false,
    ) {}
}

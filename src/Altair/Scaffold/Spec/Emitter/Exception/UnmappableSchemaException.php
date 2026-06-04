<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Spec\Emitter\Exception;

use Altair\Scaffold\Exception\ScaffoldException;

/**
 * Raised when an OpenAPI schema cannot be expressed as an Altair YAML spec
 * field — for example a `oneOf` without a discriminator, a recursive `$ref`
 * cycle, or a schema kind the framework does not yet handle.
 *
 * The JSON pointer locates the offending node inside the source document so
 * the caller can surface it verbatim to the user (or agent). {@see $reason}
 * holds the bare explanation without the pointer prefix, for callers that
 * render the location separately and would otherwise repeat it.
 */
final class UnmappableSchemaException extends ScaffoldException
{
    public function __construct(
        public readonly string $jsonPointer,
        public readonly string $reason,
    ) {
        parent::__construct(\sprintf('Unmappable schema at %s: %s', $jsonPointer, $reason));
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Emitter;

use Altair\Scaffold\Spec\Ast\Spec;

/**
 * Emits a single route entry to be merged into config/routes.php.
 *
 * The contents are a single PHP expression line; the Pipeline merges these
 * into a `return` array when it writes the routes file, so each spec can be
 * emitted independently.
 */
class RouteEmitter
{
    public function __construct(private readonly Naming $naming = new Naming()) {}

    public function emit(Spec $spec): EmittedFile
    {
        $actionFqcn = $this->naming->actionFqcn($spec);
        $entry = \sprintf(
            "    ['%s', '%s', %s::class],",
            $spec->endpoint->method,
            $spec->endpoint->path,
            '\\' . $actionFqcn,
        );

        return new EmittedFile(
            relativePath: $this->naming->routesPath(),
            contents: $entry,
            kind: EmittedFileKind::Route,
        );
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Spec\Ast;

final readonly class EndpointSpec
{
    /**
     * @param list<string> $tags
     * @param string       $resource PascalCase group (e.g. `User`) that nests the
     *                               Action/Input/Responder/test under a folder and
     *                               namespace of that name; empty keeps them flat.
     */
    public function __construct(
        public string $method,
        public string $path,
        public string $summary,
        public array $tags,
        public string $resource = '',
    ) {}
}

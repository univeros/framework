<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Spec\Ast;

final readonly class OutputResponseSpec
{
    /**
     * @param array<string, string> $body Field name -> type spec (e.g. "App\\User\\User" or "array<string, list<string>>")
     */
    public function __construct(
        public int $status,
        public array $body,
    ) {}
}

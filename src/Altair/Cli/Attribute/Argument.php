<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cli\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Argument
{
    /**
     * @param string|null $name Optional override for the public argument name; defaults to the parameter name
     */
    public function __construct(
        public string $description = '',
        public ?string $name = null,
    ) {}
}

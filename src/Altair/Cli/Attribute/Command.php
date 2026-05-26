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

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Command
{
    /**
     * @param list<string> $aliases
     */
    public function __construct(
        public string $name,
        public string $description = '',
        public array $aliases = [],
        public bool $hidden = false,
        public string $help = '',
    ) {}
}

<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Attribute;

use Attribute;

/**
 * Resolve the decorated parameter to a specific container id rather than its
 * declared type — e.g. `#[Inject(FileLogger::class)] LoggerInterface $logger`.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Inject
{
    public function __construct(public string $id) {}
}

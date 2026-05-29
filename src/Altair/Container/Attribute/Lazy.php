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
 * Mark a class so the container returns a lazy placeholder that defers real
 * construction until the instance is first touched.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Lazy {}

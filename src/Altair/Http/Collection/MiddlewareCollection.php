<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Collection;

use Altair\Structure\Queue;

/**
 * An ordered queue of PSR-15 middleware references (FQCN string, instance, or [class, method] pair).
 *
 * @extends Queue<mixed>
 */
class MiddlewareCollection extends Queue {}

<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Collection;

use Altair\Structure\Map;

/**
 * Maps a "METHOD /path" route key to its handler reference (an action FQCN).
 *
 * @extends Map<string, string>
 */
class RouteCollection extends Map {}

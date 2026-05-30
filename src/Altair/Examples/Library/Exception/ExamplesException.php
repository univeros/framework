<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Examples\Library\Exception;

use RuntimeException;

/**
 * Base exception for the examples library — every checked failure ultimately
 * derives from this so callers can catch the whole subtree with one type.
 */
class ExamplesException extends RuntimeException {}

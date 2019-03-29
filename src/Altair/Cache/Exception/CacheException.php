<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cache\Exception;

use Psr\Cache\CacheException as CacheExceptionInterface;
use RuntimeException;

class CacheException extends RuntimeException implements CacheExceptionInterface
{
}

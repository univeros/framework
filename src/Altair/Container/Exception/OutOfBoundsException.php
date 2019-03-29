<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Exception;

use Psr\Container\ContainerExceptionInterface;

class OutOfBoundsException extends \OutOfBoundsException implements ContainerExceptionInterface
{
}

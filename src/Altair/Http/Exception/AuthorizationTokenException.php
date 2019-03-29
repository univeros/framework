<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Exception;

use Altair\Http\Contracts\HttpStatusCodeInterface;
use Throwable;

class AuthorizationTokenException extends HttpException
{
    public function __construct($message = "", Throwable $previous = null)
    {
        parent::__construct($message, HttpStatusCodeInterface::HTTP_FORBIDDEN, $previous);
    }
}

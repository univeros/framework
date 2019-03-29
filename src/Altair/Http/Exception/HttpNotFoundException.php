<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Exception;

use Altair\Http\Contracts\HttpStatusCodeInterface;

class HttpNotFoundException extends HttpBadRequestException
{
    /**
     * Constructor.
     * @param string $message error message
     * @param \Exception $previous The previous exception used for the exception chaining.
     */
    public function __construct($message = null, \Exception $previous = null)
    {
        parent::__construct($message, HttpStatusCodeInterface::HTTP_NOT_FOUND, $previous);
    }
}

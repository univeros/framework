<?php
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

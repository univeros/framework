<?php
namespace Altair\Http\Exception;

use Altair\Http\Contracts\HttpStatusCodeInterface;
use Throwable;

class AuthorizationException extends HttpException
{
    public function __construct($message = "", Throwable $previous = null)
    {
        parent::__construct($message, HttpStatusCodeInterface::HTTP_UNAUTHORIZED, $previous);
    }
}

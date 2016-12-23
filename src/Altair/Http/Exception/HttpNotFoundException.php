<?php
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

<?php
namespace Altair\Http\Middleware;

use Altair\Http\Contracts\MiddlewareInterface;
use Relay\Middleware\ContentHandler;

abstract class AbstractContentHandlerMiddleware extends ContentHandler implements MiddlewareInterface
{
}

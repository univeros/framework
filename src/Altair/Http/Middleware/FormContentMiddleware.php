<?php
namespace Altair\Http\Middleware;

use Altair\Http\Contracts\MiddlewareInterface;
use Relay\Middleware\FormContentHandler;

class FormContentMiddleware extends FormContentHandler implements MiddlewareInterface
{
}

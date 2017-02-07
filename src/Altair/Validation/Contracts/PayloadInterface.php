<?php
namespace Altair\Validation\Contracts;

use Altair\Middleware\Contracts\PayloadInterface as MiddlewarePayloadInterface;

interface PayloadInterface extends MiddlewarePayloadInterface
{
    const ATTRIBUTE_SUBJECT = 'altair:validation:subject';
    const ATTRIBUTE_KEY = 'altair:validation:attribute';
    const ATTRIBUTE_RESULT = 'altair:validation:result';
    const ATTRIBUTE_FAILURES = 'altair:validation:fail';
}

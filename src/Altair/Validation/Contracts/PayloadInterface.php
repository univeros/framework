<?php
namespace Altair\Validation\Contracts;

use Altair\Middleware\Contracts\PayloadInterface as MiddlewarePayloadInterface;

interface PayloadInterface extends MiddlewarePayloadInterface
{
    const SUBJECT_KEY = 'altair:validation:subject';
    const ATTRIBUTE_KEY = 'altair:validation:attribute';
    const RESULT_KEY = 'altair:validation:result';
    const FAILURES_KEY = 'altair:validation:fail';
}

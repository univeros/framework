<?php
namespace Altair\Sanitation\Contracts;

use Altair\Middleware\Contracts\PayloadInterface as MiddlewarePayloadInterface;

interface PayloadInterface extends MiddlewarePayloadInterface
{
    const ATTRIBUTE_SUBJECT = 'altair:sanitation:subject';
    const ATTRIBUTE_KEY = 'altair:sanitation:attribute';
}

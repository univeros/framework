<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation\Contracts;

use Altair\Middleware\Contracts\PayloadInterface as MiddlewarePayloadInterface;

interface PayloadInterface extends MiddlewarePayloadInterface
{
    public const ATTRIBUTE_SUBJECT = 'altair:validation:subject';
    public const ATTRIBUTE_KEY = 'altair:validation:attribute';
    public const ATTRIBUTE_RESULT = 'altair:validation:result';
    public const ATTRIBUTE_FAILURES = 'altair:validation:fail';
}

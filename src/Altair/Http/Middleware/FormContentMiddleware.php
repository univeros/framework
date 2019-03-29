<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Middleware;

use Altair\Http\Contracts\MiddlewareInterface;
use Relay\Middleware\FormContentHandler;

class FormContentMiddleware extends FormContentHandler implements MiddlewareInterface
{
}

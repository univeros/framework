<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Contracts;

use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;

interface MiddlewareInterface extends PsrMiddlewareInterface
{
    public const string ATTRIBUTE_IP_ADDRESS = 'altair:http:ip-address';

    public const string ATTRIBUTE_ACTION = 'altair:http:action';

    public const string ATTRIBUTE_FORMAT = 'altair:http:format';

    public const string ATTRIBUTE_USERNAME = 'altair:http:username';

    public const string ATTRIBUTE_EXCEPTION = 'altair:http:exception';

    public const string ATTRIBUTE_CSRF_HEADER = 'X-XSRF-TOKEN';
}

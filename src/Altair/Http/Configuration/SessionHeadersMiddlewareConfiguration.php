<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Http\Contracts\CacheLimiterInterface;
use Altair\Http\Support\NoCacheLimiter;

class SessionHeadersMiddlewareConfiguration implements ConfigurationInterface
{
    public function apply(Container $container)
    {
        // forced or fire errors if not set like this?
        ini_set('session.use_trans_sid', false);
        ini_set('session.use_cookies', false);
        ini_set('session.use_only_cookies', true);

        $container
            ->alias(CacheLimiterInterface::class, NoCacheLimiter::class);
    }
}

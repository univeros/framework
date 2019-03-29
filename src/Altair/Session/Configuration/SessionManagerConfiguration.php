<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Session\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Session\Contracts\SessionManagerInterface;
use Altair\Session\SessionManager;

class SessionManagerConfiguration implements ConfigurationInterface
{
    public function apply(Container $container)
    {
        // This manager should be working with Altair's Http Component
        // ServerRequestInterface is already configured on HttpMessageConfiguration::class
        // ensure the application makes use of it.
        $container->alias(SessionManagerInterface::class, SessionManager::class);
    }
}

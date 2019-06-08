<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Session\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Altair\Container\Definition;
use Altair\Session\Handler\PredisSessionHandler;
use Predis\Client;
use Predis\ClientInterface;
use SessionHandlerInterface;

class PredisSessionHandlerConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container): void
    {
        // This definition is pretty basic. To improve this configuration, check the multiple connection parameters
        // on https://github.com/nrk/predis/wiki/Connection-Parameters to configure on your .env file.
        $definition = new Definition([
            ':parameters' => $this->env->get('SESSION_REDIS_URI', 'tcp://127.0.0.1:6379')
        ]);

        $container
            ->define(Client::class, $definition)
            ->alias(ClientInterface::class, Client::class)
            ->alias(SessionHandlerInterface::class, PredisSessionHandler::class);
    }
}

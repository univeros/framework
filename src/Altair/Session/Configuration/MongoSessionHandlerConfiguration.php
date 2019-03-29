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
use Altair\Session\Handler\MongoSessionHandler;
use MongoDB\Client;
use SessionHandlerInterface;

class MongoSessionHandlerConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container)
    {
        $factory = function () {
            return (new Client(
                $this->env->get('SESSION_MONGO_URI', 'mongodb://12.0.0.1/')
            ))->selectCollection(
                $this->env->get('SESSION_MONGO_DB', 'session_db'),
                $this->env->get('SESSION_MONGO_COLLECTION', 'session_collection')
            );
        };

        $container
            ->delegate(MongoSessionHandler::class, $factory)
            ->alias(SessionHandlerInterface::class, MongoSessionHandler::class);
    }
}

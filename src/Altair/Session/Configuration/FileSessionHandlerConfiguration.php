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
use Altair\Session\Handler\FileSessionHandler;
use SessionHandlerInterface;

class FileSessionHandlerConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container): void
    {
        $definition = new Definition([
            ':path' => $this->env->get('SESSION_FILE_PATH'),
            ':minutes' => $this->env->get('SESSION_FILE_MINUTES')
        ]);

        $container
            ->define(FileSessionHandler::class, $definition)
            ->alias(SessionHandlerInterface::class, FileSessionHandler::class);
    }
}

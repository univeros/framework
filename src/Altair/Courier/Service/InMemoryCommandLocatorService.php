<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Courier\Service;

use Altair\Courier\Contracts\CommandInterface;
use Altair\Courier\Contracts\InMemoryCommandLocatorServiceInterface;
use Altair\Courier\Exception\UnknownCommandMessageNameException;
use Altair\Courier\Support\MessageCommandMap;
use Override;

class InMemoryCommandLocatorService implements InMemoryCommandLocatorServiceInterface
{
    protected MessageCommandMap $map;

    /**
     * InMemoryCommandLocatorService constructor.
     */
    public function __construct(?MessageCommandMap $map = null)
    {
        $this->map = $map ?? new MessageCommandMap();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withMap(MessageCommandMap $map): InMemoryCommandLocatorServiceInterface
    {
        return new static($map);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function add(string $messageName, string $commandName): InMemoryCommandLocatorServiceInterface
    {
        $this->map->put($messageName, $commandName);

        return $this;
    }

    #[Override]
    public function has(string $name): bool
    {
        return $this->map->hasKey($name);
    }

    /**
     * @throws UnknownCommandMessageNameException
     */
    #[Override]
    public function get(string $name): CommandInterface
    {
        if (!$this->has($name)) {
            throw new UnknownCommandMessageNameException(\sprintf('Unknown message name: %s', $name));
        }

        return $this->getInstance($name);
    }

    /**
     * Returns an instance of the command found on the map. After is found, we check whether the command was previously
     * created, if not, create instance and store in map so it can later be accessed.
     *
     *
     */
    protected function getInstance(string $name): CommandInterface
    {
        $command = $this->map->get($name);
        if (!\is_object($command)) {
            $command = new $command();
            $this->map->put($name, $command);
        }

        return $command;
    }
}

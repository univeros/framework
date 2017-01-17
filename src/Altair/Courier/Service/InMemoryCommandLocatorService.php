<?php
namespace Altair\Courier\Service;

use Altair\Courier\Contracts\CommandInterface;
use Altair\Courier\Contracts\CommandLocatorServiceInterface;
use Altair\Courier\Contracts\InMemoryCommandLocatorServiceInterface;
use Altair\Courier\Exception\UnknownCommandMessageNameException;
use Altair\Courier\Support\MessageCommandMap;

class InMemoryCommandLocatorService implements InMemoryCommandLocatorServiceInterface
{
    /**
     * @var MessageCommandMap
     */
    protected $map;

    /**
     * InMemoryCommandLocatorService constructor.
     *
     * @param MessageCommandMap|null $map
     */
    public function __construct(MessageCommandMap $map = null)
    {
        $this->map = $map?? new MessageCommandMap();
    }

    /**
     * @inheritdoc
     */
    public function withMap(MessageCommandMap $map): InMemoryCommandLocatorServiceInterface
    {
        return new static($map);
    }

    /**
     * @inheritdoc
     */
    public function add(string $messageName, string $commandName): InMemoryCommandLocatorServiceInterface
    {
        $this->map->put($messageName, $commandName);

        return $this;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name): bool
    {
        return $this->map->hasKey($name);
    }

    /**
     * @param string $name
     *
     * @return CommandInterface
     * @throws UnknownCommandMessageNameException
     */
    public function get(string $name): CommandInterface
    {
        if (!$this->has($name)) {
            throw new UnknownCommandMessageNameException(sprintf('Unknown message name: %s', $name));
        }

        return $this->getInstance($name);
    }

    /**
     * Returns an instance of the command found on the map. After is found, we check whether the command was previously
     * created, if not, create instance and store in map so it can later be accessed.
     *
     * @param string $name
     *
     * @return CommandInterface
     */
    protected function getInstance(string $name): CommandInterface
    {
        $command = $this->map->get($name);
        if (!is_object($command)) {
            $command = new $command();
            $this->map->put($name, $command);
        }

        return $command;
    }
}

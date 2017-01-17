<?php
namespace Altair\Courier\Service;

use Altair\Courier\Contracts\CommandInterface;
use Altair\Courier\Contracts\CommandLocatorServiceInterface;
use Altair\Courier\Exception\UnknownCommandMessageNameException;

class CallableCommandLocatorService implements CommandLocatorServiceInterface
{
    /**
     * @var callable
     */
    protected $callable;

    /**
     * CallableCommandLocatorService constructor.
     *
     * @param callable $callable
     */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    /**
     * @inheritdoc
     */
    public function has(string $name): bool
    {
        return call_user_func($this->callable, $name) !== null;
    }

    /**
     * @inheritdoc
     */
    public function get(string $name): CommandInterface
    {
        if (!$this->has($name)) {
            throw new UnknownCommandMessageNameException(sprintf('Unknown message name: %s', $name));
        }

        return call_user_func($this->callable, $name);
    }
}

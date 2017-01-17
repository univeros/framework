<?php
namespace Altair\Courier\Middleware;

use Altair\Courier\Contracts\CommandLocatorServiceInterface;
use Altair\Courier\Contracts\CommandMessageInterface;
use Altair\Courier\Contracts\CommandMessageNameResolverInterface;
use Altair\Courier\Contracts\CommandMiddlewareInterface;

class CommandHandlerMiddleware implements CommandMiddlewareInterface
{
    /**
     * @var CommandLocatorServiceInterface
     */
    protected $commandLocator;
    /**
     * @var CommandMessageNameResolverInterface
     */
    protected $nameResolver;

    /**
     * HandlerMiddleware constructor.
     *
     * @param CommandLocatorServiceInterface $commandLocator
     * @param CommandMessageNameResolverInterface $nameResolver
     */
    public function __construct(
        CommandLocatorServiceInterface $commandLocator,
        CommandMessageNameResolverInterface $nameResolver
    ) {
        $this->commandLocator = $commandLocator;
        $this->nameResolver = $nameResolver;
    }

    /**
     * @inheritdoc
     */
    public function handle(CommandMessageInterface $message, callable $next)
    {
        $name = $this->nameResolver->resolve($message);
        // purposely call get to fire error in case there is no command found for that message
        // that way you can try/catch the error and use your own Psr\Log\LoggerInterface
        // @see CommandLockerMiddleware
        $command = $this->commandLocator->get($name);
        $command->exec($message);
        $next($message);
    }
}

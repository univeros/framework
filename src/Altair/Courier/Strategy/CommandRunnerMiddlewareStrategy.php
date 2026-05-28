<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Courier\Strategy;

use Altair\Courier\Contracts\CommandMessageInterface;
use Altair\Courier\Contracts\CommandMiddlewareInterface;
use Altair\Courier\Contracts\CommandRunnerStrategyInterface;
use Altair\Courier\Contracts\MiddlewareResolverInterface;
use Altair\Courier\Exception\InvalidCommandMiddlewareException;
use Closure;
use Override;

/**
 * @phpstan-consistent-constructor
 */
class CommandRunnerMiddlewareStrategy implements CommandRunnerStrategyInterface
{
    /**
     * @var array<int, CommandMiddlewareInterface|class-string<CommandMiddlewareInterface>>
     */
    protected array $middlewares;

    /**
     * CommandRunnerMiddlewareStrategy constructor.
     *
     * @param array<int, CommandMiddlewareInterface|class-string<CommandMiddlewareInterface>>|null $middlewares
     */
    public function __construct(?array $middlewares = null, protected ?MiddlewareResolverInterface $resolver = null)
    {
        $this->middlewares = $middlewares ?? [];
    }

    /**
     * Returns a new instance with
     *
     * @param array<int, CommandMiddlewareInterface|class-string<CommandMiddlewareInterface>> $middlewares
     *
     * @throws InvalidCommandMiddlewareException
     */
    public function withMiddlewares(array $middlewares): CommandRunnerStrategyInterface
    {
        foreach ($middlewares as $middleware) {
            if (!is_subclass_of($middleware, CommandMiddlewareInterface::class)) {
                throw new InvalidCommandMiddlewareException(
                    \sprintf(
                        'Invalid command middleware %s does not implement %s',
                        $middleware,
                        CommandMiddlewareInterface::class
                    )
                );
            }
        }

        return new static($middlewares);
    }

    public function add(CommandMiddlewareInterface $middleware): CommandRunnerStrategyInterface
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    /**
     * Initiates middleware sequence.
     */
    #[Override]
    public function run(CommandMessageInterface $message): void
    {
        \call_user_func($this->call(0), $message);
    }

    /**
     * Fire up middleware chain.
     *
     *
     */
    protected function call(int $index): Closure
    {
        if (!isset($this->middlewares[$index])) {
            return function (): void {};
        }

        $middleware = $this->middlewares[$index];

        if ($this->resolver instanceof MiddlewareResolverInterface) {
            $middleware = \call_user_func($this->resolver, $middleware);
            $this->middlewares[$index] = $middleware;
        }

        return function ($message) use ($middleware, $index): void {
            $middleware->handle($message, $this->call($index + 1));
        };
    }
}

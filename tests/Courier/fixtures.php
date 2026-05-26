<?php

namespace Altair\Tests\Courier;

use Altair\Courier\Contracts\CommandInterface;
use Altair\Courier\Contracts\CommandMessageInterface;
use Altair\Courier\Contracts\CommandMiddlewareInterface;
use Altair\Courier\Support\LogMessage;
use Altair\Courier\Traits\LogMessageTrait;
use Psr\Log\LogLevel;

class TestCommand implements CommandInterface
{
    #[\Override]
    public function exec(CommandMessageInterface $message): void
    {
        if ($message instanceof TestCommandMessage) {
            $message->value = 'executed';
        }
    }
}

class TestCommandInjectsErrorLogMessage implements CommandInterface
{
    #[\Override]
    public function exec(CommandMessageInterface $message): void
    {
        $message->setLogMessage(new LogMessage('test message', LogLevel::ERROR));
    }
}

class TestCommandMessage implements CommandMessageInterface
{
    use LogMessageTrait;

    public $value;

    protected $logMessage;

    #[\Override]
    public function getName(): string
    {
        return 'TestCommandMessage';
    }
}

class TestCommandWithErrorLogMessage implements CommandMessageInterface
{
    use LogMessageTrait;

    #[\Override]
    public function getName(): string
    {
        return 'TestCommandWithErrorLogMessage';
    }
}

class StubMiddleware implements CommandMiddlewareInterface
{
    private $handler;

    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    #[\Override]
    public function handle(CommandMessageInterface $message, callable $next): void
    {
        call_user_func($this->handler, $message);

        $next();
    }
}

class CallableClass
{
    public $map = [
        TestCommandMessage::class => TestCommand::class,
        TestCommandWithErrorLogMessage::class => TestCommandInjectsErrorLogMessage::class
    ];

    public function __invoke($name): ?object
    {
        if (isset($this->map[$name])) {
            return new $this->map[$name];
        }
        return null;
    }
}

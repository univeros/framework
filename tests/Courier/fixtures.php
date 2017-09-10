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
    public function exec(CommandMessageInterface $message)
    {
        if ($message instanceof TestCommandMessage) {
            $message->value = 'executed';
        }
    }
}

class TestCommandInjectsErrorLogMessage implements CommandInterface
{
    public function exec(CommandMessageInterface $message)
    {
        $message->withLogMessage(new LogMessage('test message', LogLevel::ERROR));
    }
}

class TestCommandMessage implements CommandMessageInterface
{
    use LogMessageTrait;

    public $value;
    protected $logMessage;

    public function getName(): string
    {
        return 'TestCommandMessage';
    }
}

class TestCommandWithErrorLogMessage implements CommandMessageInterface
{
    use LogMessageTrait;

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

    public function handle(CommandMessageInterface $message, callable $next)
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

    public function __invoke($name)
    {
        if (isset($this->map[$name])) {
            return new $this->map[$name];
        }
    }
}

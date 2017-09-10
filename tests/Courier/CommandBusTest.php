<?php

namespace Altair\Tests\Courier;

use Altair\Container\Container;
use Altair\Courier\CommandBus;
use Altair\Courier\Middleware\CommandHandlerMiddleware;
use Altair\Courier\Middleware\CommandLockerMiddleware;
use Altair\Courier\Resolver\ClassCommandMessageNameResolver;
use Altair\Courier\Resolver\CommandMessageNameResolver;
use Altair\Courier\Resolver\MiddlewareResolver;
use Altair\Courier\Service\InMemoryCommandLocatorService;
use Altair\Courier\Strategy\CommandRunnerExecStrategy;
use Altair\Courier\Strategy\CommandRunnerMiddlewareStrategy;
use Altair\Courier\Support\LogMessage;
use Altair\Courier\Support\MessageCommandMap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class CommandBusTest extends TestCase
{
    public function testCommandsUsingExecStrategyWithMessageNameResolver()
    {
        $mapper = new MessageCommandMap(
            [
                'TestCommandMessage' => TestCommand::class,
                'TestCommandWithErrorLogMessage' => TestCommandInjectsErrorLogMessage::class
            ]
        );

        $resolver = new CommandMessageNameResolver();
        $locator = new InMemoryCommandLocatorService($mapper);
        $strategy = new CommandRunnerExecStrategy($locator, $resolver);
        $bus = new CommandBus($strategy);

        $testCommandMessage = new TestCommandMessage();
        $this->assertNull($testCommandMessage->value);
        $bus->handle($testCommandMessage);
        $this->assertEquals('executed', $testCommandMessage->value);

        $testCommandInjectsErrorLogMessage = new TestCommandWithErrorLogMessage();
        $this->assertNotInstanceOf(LogMessage::class, $testCommandInjectsErrorLogMessage->getLogMessage());
        $bus->handle($testCommandInjectsErrorLogMessage);
        $this->assertInstanceOf(LogMessage::class, $testCommandInjectsErrorLogMessage->getLogMessage());
        $this->assertEquals(LogLevel::ERROR, $testCommandInjectsErrorLogMessage->getLogMessage()->getLevel());
    }

    public function testCommandsUsingExecStrategyWithClassNameResolver()
    {
        $mapper = new MessageCommandMap(
            [
                TestCommandMessage::class => TestCommand::class,
                TestCommandWithErrorLogMessage::class => TestCommandInjectsErrorLogMessage::class
            ]
        );

        $resolver = new ClassCommandMessageNameResolver();
        $locator = new InMemoryCommandLocatorService($mapper);
        $strategy = new CommandRunnerExecStrategy($locator, $resolver);
        $bus = new CommandBus($strategy);

        $testCommandMessage = new TestCommandMessage();
        $this->assertNull($testCommandMessage->value);
        $bus->handle($testCommandMessage);
        $this->assertEquals('executed', $testCommandMessage->value);

        $testCommandInjectsErrorLogMessage = new TestCommandWithErrorLogMessage();
        $this->assertNotInstanceOf(LogMessage::class, $testCommandInjectsErrorLogMessage->getLogMessage());
        $bus->handle($testCommandInjectsErrorLogMessage);
        $this->assertInstanceOf(LogMessage::class, $testCommandInjectsErrorLogMessage->getLogMessage());
        $this->assertEquals(LogLevel::ERROR, $testCommandInjectsErrorLogMessage->getLogMessage()->getLevel());
    }

    public function testItFinishesHandlingAMessageBeforeHandlingTheNext()
    {
        $originalMessage = new TestCommandMessage();
        $newMessage = new TestCommandMessage();
        $flow = [];
        $bus = null;

        $mapper = new MessageCommandMap(
            [
                'TestCommandMessage' => TestCommand::class,
                'TestCommandWithErrorLogMessage' => TestCommandInjectsErrorLogMessage::class
            ]
        );

        $resolver = new CommandMessageNameResolver();
        $locator = new InMemoryCommandLocatorService($mapper);

        $strategy = new CommandRunnerMiddlewareStrategy(
            [
                new CommandLockerMiddleware(),
                new CommandHandlerMiddleware($locator, $resolver),
            ]
        );
        $bus = new CommandBus($strategy);
        $strategy->add(
            new StubMiddleware(
                function ($actualMessage) use ($originalMessage, $newMessage, $bus, &$flow) {
                    if ($actualMessage === $originalMessage) {
                        $flow[] = 'start handling original message';
                        // while handling the original we trigger a new message
                        $bus->handle($newMessage);
                        $flow[] = 'finished handling original message';
                    } elseif ($actualMessage === $newMessage) {
                        $flow[] = 'start handling new message';
                        $flow[] = 'finished handling new message';
                    }
                }
            )
        );

        $bus->handle($originalMessage);

        $this->assertSame(
            [
                'start handling original message',
                'finished handling original message',
                'start handling new message',
                'finished handling new message'
            ],
            $flow
        );
    }
    public function testItFinishesHandlingAMessageBeforeHandlingTheNextWithMiddlewareResolver()
    {
        $originalMessage = new TestCommandMessage();
        $newMessage = new TestCommandMessage();
        $flow = [];
        $bus = null;

        $mapper = new MessageCommandMap(
            [
                'TestCommandMessage' => TestCommand::class,
                'TestCommandWithErrorLogMessage' => TestCommandInjectsErrorLogMessage::class
            ]
        );

        $resolver = new CommandMessageNameResolver();
        $locator = new InMemoryCommandLocatorService($mapper);

        $strategy = new CommandRunnerMiddlewareStrategy(
            [
                CommandLockerMiddleware::class,
                new CommandHandlerMiddleware($locator, $resolver),
            ],
            new MiddlewareResolver(new Container())
        );
        $bus = new CommandBus($strategy);
        $strategy->add(
            new StubMiddleware(
                function ($actualMessage) use ($originalMessage, $newMessage, $bus, &$flow) {
                    if ($actualMessage === $originalMessage) {
                        $flow[] = 'start handling original message';
                        // while handling the original we trigger a new message
                        $bus->handle($newMessage);
                        $flow[] = 'finished handling original message';
                    } elseif ($actualMessage === $newMessage) {
                        $flow[] = 'start handling new message';
                        $flow[] = 'finished handling new message';
                    }
                }
            )
        );

        $bus->handle($originalMessage);

        $this->assertSame(
            [
                'start handling original message',
                'finished handling original message',
                'start handling new message',
                'finished handling new message'
            ],
            $flow
        );
    }
}

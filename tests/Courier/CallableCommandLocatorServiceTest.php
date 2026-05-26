<?php

namespace Altair\Tests\Courier;

use Altair\Courier\Exception\UnknownCommandMessageNameException;
use Altair\Courier\Service\CallableCommandLocatorService;
use PHPUnit\Framework\TestCase;

class CallableCommandLocatorServiceTest extends TestCase
{
    public function testItFindsCorrectCommandHandlerUsingFunctionCallable(): void
    {
        $callable = static fn($name): TestCommand|TestCommandInjectsErrorLogMessage|null => match ($name) {
            'TestCommandMessage' => new TestCommand(),
            'TestCommandWithErrorLogMessage' => new TestCommandInjectsErrorLogMessage(),
            default => null,
        };

        $locator = new CallableCommandLocatorService($callable);

        $this->assertTrue($locator->has('TestCommandMessage'));
        $this->assertTrue($locator->has('TestCommandWithErrorLogMessage'));
        $command = $locator->get('TestCommandMessage');
        $this->assertInstanceOf(TestCommand::class, $command);
        $command = $locator->get('TestCommandWithErrorLogMessage');
        $this->assertInstanceOf(TestCommandInjectsErrorLogMessage::class, $command);
    }

    public function testItFindsCorrectCommandHandlerUsingCallableClass(): void
    {
        $callable = new CallableClass();

        $locator = new CallableCommandLocatorService($callable);

        $this->assertTrue($locator->has(TestCommandMessage::class));
        $this->assertTrue($locator->has(TestCommandWithErrorLogMessage::class));
        $command = $locator->get(TestCommandMessage::class);
        $this->assertInstanceOf(TestCommand::class, $command);
        $command = $locator->get(TestCommandWithErrorLogMessage::class);
        $this->assertInstanceOf(TestCommandInjectsErrorLogMessage::class, $command);
    }

    public function testItThrowsException(): void
    {
        $locator = new CallableCommandLocatorService(
            function (): void {
            }
        );
        $this->assertFalse($locator->has('none'));
        $this->expectException(UnknownCommandMessageNameException::class);
        $locator->get('none');
    }
}

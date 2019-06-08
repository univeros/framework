<?php

namespace Altair\Tests\Courier;

use Altair\Courier\Exception\UnknownCommandMessageNameException;
use Altair\Courier\Service\CallableCommandLocatorService;
use PHPUnit\Framework\TestCase;

class CallableCommandLocatorServiceTest extends TestCase
{
    public function testItFindsCorrectCommandHandlerUsingFunctionCallable()
    {
        $callable = static function ($name) {
            switch ($name) {
                case 'TestCommandMessage':
                    return new TestCommand();
                case 'TestCommandWithErrorLogMessage':
                    return new TestCommandInjectsErrorLogMessage();
            }

            return null;
        };

        $locator = new CallableCommandLocatorService($callable);

        $this->assertTrue($locator->has('TestCommandMessage'));
        $this->assertTrue($locator->has('TestCommandWithErrorLogMessage'));
        $command = $locator->get('TestCommandMessage');
        $this->assertInstanceOf(TestCommand::class, $command);
        $command = $locator->get('TestCommandWithErrorLogMessage');
        $this->assertInstanceOf(TestCommandInjectsErrorLogMessage::class, $command);
    }

    public function testItFindsCorrectCommandHandlerUsingCallableClass()
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

    public function testItThrowsException()
    {
        $locator = new CallableCommandLocatorService(
            function () {
            }
        );
        $this->assertFalse($locator->has('none'));
        $this->expectException(UnknownCommandMessageNameException::class);
        $locator->get('none');
    }
}

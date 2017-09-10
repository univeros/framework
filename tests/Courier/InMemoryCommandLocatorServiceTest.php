<?php

namespace Altair\Tests\Courier;

use Altair\Courier\Exception\UnknownCommandMessageNameException;
use Altair\Courier\Service\InMemoryCommandLocatorService;
use Altair\Courier\Support\MessageCommandMap;
use PHPUnit\Framework\TestCase;

class InMemoryCommandLocatorServiceTest extends TestCase
{
    public function testItFindsCorrectCommandHandler()
    {
        $mapper = new MessageCommandMap(
            [
                'TestCommandMessage' => TestCommand::class,
                'TestCommandWithErrorLogMessage' => TestCommandInjectsErrorLogMessage::class
            ]
        );

        $locator = new InMemoryCommandLocatorService($mapper);

        $this->assertTrue($locator->has('TestCommandMessage'));
        $this->assertTrue($locator->has('TestCommandWithErrorLogMessage'));
        $command = $locator->get('TestCommandMessage');
        $this->assertInstanceOf(TestCommand::class, $command);
        $command = $locator->get('TestCommandWithErrorLogMessage');
        $this->assertInstanceOf(TestCommandInjectsErrorLogMessage::class, $command);
    }

    public function testItThrowsException()
    {
        $locator = new InMemoryCommandLocatorService();
        $this->assertFalse($locator->has('none'));
        $this->expectException(UnknownCommandMessageNameException::class);
        $locator->get('none');
    }

    public function testWithMapReturnsANewInstance()
    {
        $mapper = new MessageCommandMap(
            [
                'TestCommandMessage' => TestCommand::class,
                'TestCommandWithErrorLogMessage' => TestCommandInjectsErrorLogMessage::class
            ]
        );

        $locator = new InMemoryCommandLocatorService();

        $this->assertNotSame($locator, $locator->withMap($mapper));
    }
}

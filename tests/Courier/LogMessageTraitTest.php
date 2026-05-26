<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Courier;

use Altair\Courier\Contracts\CommandMessageInterface;
use Altair\Courier\Support\LogMessage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

require_once __DIR__ . '/fixtures.php';

final class LogMessageTraitTest extends TestCase
{
    public function testGetLogMessageReturnsNullByDefault(): void
    {
        $message = new TestCommandMessage();

        self::assertNull($message->getLogMessage());
    }

    public function testSetLogMessageMutatesInPlaceAndIsReadable(): void
    {
        $message = new TestCommandMessage();
        $log = new LogMessage('boom', LogLevel::ERROR);

        $message->setLogMessage($log);

        self::assertSame($log, $message->getLogMessage());
    }

    public function testInterfaceContractIsSetterNotWither(): void
    {
        $reflection = new \ReflectionClass(CommandMessageInterface::class);

        self::assertTrue(
            $reflection->hasMethod('setLogMessage'),
            'CommandMessageInterface must expose setLogMessage (mutator) per the bus dispatch contract.',
        );
        self::assertFalse(
            $reflection->hasMethod('withLogMessage'),
            'withLogMessage was renamed in #47 — it misused the framework with* immutability idiom.',
        );

        $method = $reflection->getMethod('setLogMessage');
        self::assertSame('void', (string) $method->getReturnType());
    }
}

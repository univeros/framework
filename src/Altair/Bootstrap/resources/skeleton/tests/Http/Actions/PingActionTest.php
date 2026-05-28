<?php

declare(strict_types=1);

namespace Tests\Http\Actions;

use App\Http\Actions\PingAction;
use App\Http\Inputs\PingInput;
use App\Http\Responders\PingResponder;
use PHPUnit\Framework\TestCase;

final class PingActionTest extends TestCase
{
    public function testActionWiresInputResponderAndDomain(): void
    {
        $action = new PingAction();

        self::assertSame(PingInput::class, $action->getInputClassName());
        self::assertSame(PingResponder::class, $action->getResponderClassName());
    }

    public function testResponderDeclaresThe200Status(): void
    {
        self::assertContains(200, PingResponder::statuses());
    }
}

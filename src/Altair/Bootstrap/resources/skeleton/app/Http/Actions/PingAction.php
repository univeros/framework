<?php

declare(strict_types=1);

namespace App\Http\Actions;

use Altair\Http\Base\Action;
use App\Health\Ping;
use App\Http\Inputs\PingInput;
use App\Http\Responders\PingResponder;

/**
 * Wires GET /ping to its domain, input DTO and responder.
 */
final class PingAction extends Action
{
    public function __construct()
    {
        parent::__construct(
            domain: Ping::class,
            responder: PingResponder::class,
            input: PingInput::class,
        );
    }
}

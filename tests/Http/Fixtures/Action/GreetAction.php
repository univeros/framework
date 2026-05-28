<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Fixtures\Action;

use Altair\Http\Base\Action;

final class GreetAction extends Action
{
    public function __construct()
    {
        parent::__construct(
            domain: GreetDomain::class,
            responder: JsonResponder::class,
            input: GreetInput::class,
        );
    }
}

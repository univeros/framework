<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Fixtures\Action;

use Altair\Http\Base\Action;

final class LegacyAction extends Action
{
    public function __construct()
    {
        parent::__construct(
            domain: LegacyDomain::class,
            responder: JsonResponder::class,
            input: LegacyInput::class,
        );
    }
}

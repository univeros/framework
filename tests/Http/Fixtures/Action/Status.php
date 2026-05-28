<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Fixtures\Action;

enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

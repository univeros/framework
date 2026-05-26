<?php

declare(strict_types=1);

namespace Altair\Tests\Cli\Fixture;

enum Role: string
{
    case Admin = 'admin';
    case Member = 'member';
    case Guest = 'guest';
}

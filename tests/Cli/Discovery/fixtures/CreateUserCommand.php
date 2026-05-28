<?php

declare(strict_types=1);

namespace Altair\Tests\Cli\Discovery\fixtures;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Tests\Cli\Fixture\Role;

#[Command(
    name: 'fixture:create-user',
    description: 'Create a fixture user',
    aliases: ['fixture:users:add'],
)]
final class CreateUserCommand
{
    public function __invoke(
        #[Argument(description: 'The user email')]
        string $email,
        #[Option(description: 'Initial password', short: 'p')]
        ?string $password = null,
        #[Option(description: 'User role', short: 'r')]
        Role $role = Role::Member,
        #[Option(description: 'Skip welcome email')]
        bool $silent = false,
    ): int {
        return 0;
    }
}

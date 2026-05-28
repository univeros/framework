<?php

declare(strict_types=1);

namespace Altair\Tests\Cli\Fixture;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;

#[Command(
    name: 'users:create',
    description: 'Create a new user account',
    aliases: ['users:add'],
    help: 'Detailed help block.',
)]
final readonly class CreateUserIntegrationCommand
{
    public function __construct(
        private SpyUserRepository $repository,
    ) {
    }

    public function __invoke(
        #[Argument(description: 'The user email')]
        string $email,
        #[Option(description: 'Initial password (random if omitted)', short: 'p')]
        ?string $password = null,
        #[Option(description: 'User role', short: 'r')]
        Role $role = Role::Member,
        #[Option(description: 'Skip welcome email')]
        bool $silent = false,
    ): int {
        $this->repository->create($email, $password, $role, $silent);

        return 0;
    }
}

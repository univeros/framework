<?php

declare(strict_types=1);

namespace Altair\Tests\Cli\Fixture;

class SpyUserRepository
{
    /** @var list<array{email: string, password: ?string, role: Role, silent: bool}> */
    public array $calls = [];

    public function create(string $email, ?string $password, Role $role, bool $silent): void
    {
        $this->calls[] = [
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'silent' => $silent,
        ];
    }
}

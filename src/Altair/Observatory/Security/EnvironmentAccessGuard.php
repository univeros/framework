<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory\Security;

use Override;

/**
 * The default guard: deny unless Observatory is explicitly enabled AND the app
 * runs in a non-production environment.
 *
 * This is fail-closed — an unset flag or an unrecognised environment denies
 * access, so a misconfigured production deploy never exposes the panel.
 */
final readonly class EnvironmentAccessGuard implements AccessGuardInterface
{
    /**
     * @param list<string> $allowedEnvironments
     */
    public function __construct(
        private bool $enabled,
        private string $environment,
        private array $allowedEnvironments = ['local', 'development', 'dev', 'testing'],
    ) {}

    #[Override]
    public function allows(): bool
    {
        return $this->enabled && \in_array($this->environment, $this->allowedEnvironments, true);
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Doctor\Check;

use Altair\Doctor\Contracts\CheckInterface;
use Altair\Doctor\Result\AgentAction;
use Altair\Doctor\Result\CheckResult;
use Closure;
use Override;

/**
 * Verifies every `ext-*` the project requires is actually loaded. The
 * extension probe is injectable so the check is unit-testable without
 * depending on the test host's loaded extensions.
 */
final readonly class ExtensionsLoadedCheck implements CheckInterface
{
    /**
     * @var Closure(string): bool
     */
    private Closure $isLoaded;

    /**
     * @param list<string>              $required ext names without the `ext-` prefix, e.g. ['redis', 'pdo']
     * @param (Closure(string): bool)|null $isLoaded
     */
    public function __construct(
        private array $required,
        ?Closure $isLoaded = null,
    ) {
        $this->isLoaded = $isLoaded ?? \extension_loaded(...);
    }

    #[Override]
    public function name(): string
    {
        return 'extensions_loaded';
    }

    #[Override]
    public function dependsOn(): array
    {
        return [];
    }

    #[Override]
    public function run(): CheckResult
    {
        $missing = array_values(array_filter(
            $this->required,
            fn(string $ext): bool => !($this->isLoaded)($ext),
        ));

        if ($missing === []) {
            return CheckResult::ok(
                $this->name(),
                $this->required === []
                    ? 'No required extensions.'
                    : 'All required extensions loaded: ' . implode(', ', $this->required) . '.',
            );
        }

        return CheckResult::error(
            $this->name(),
            'Missing required extensions: ' . implode(', ', $missing) . '.',
            'Install and enable the listed PHP extensions.',
            AgentAction::installDep('ext-' . $missing[0]),
        );
    }
}

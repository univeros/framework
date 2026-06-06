<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Module\Contracts;

use Altair\Configuration\Contracts\ConfigurationInterface;

/**
 * A module is a self-contained, installable feature for a host Altair app.
 *
 * It is, first and foremost, a {@see ConfigurationInterface}: its `apply()`
 * registers the module's own service bindings into the container. Beyond that,
 * a module opts into framework surfaces by ALSO implementing the narrow
 * provider contracts in this namespace:
 *
 *   - {@see RoutesProviderInterface}              — contribute HTTP routes
 *   - {@see MiddlewareProviderInterface}          — contribute PSR-15 middleware
 *   - {@see EntityDirectoriesProviderInterface}   — contribute Cycle entity dirs
 *   - {@see MigrationDirectoriesProviderInterface} — contribute migration dirs
 *
 * Consumers (the HTTP front controller, the schema provider, the migrate
 * commands) discover every registered module through the container tag
 * {@see \Altair\Module\ModuleConfiguration::MODULE_TAG} and branch on
 * `instanceof` for each capability — so a service-only module never has to
 * pull in `univeros/http` or `univeros/persistence`.
 */
interface ModuleInterface extends ConfigurationInterface
{
    /**
     * A short, stable identifier for the module, e.g. "user-management".
     *
     * Used in diagnostics (doctor, introspection) and logs.
     */
    public function name(): string;
}

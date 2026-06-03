<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Bootstrap\Cli;

use Altair\Bootstrap\Exception\BootstrapException;
use Altair\Bootstrap\SkeletonGenerator;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;

use const STDERR;

/**
 * `bin/altair module:new` — scaffold a pluggable Univeros module package.
 *
 * ```bash
 * bin/altair module:new --dir=user-management --name=acme/user-management
 * bin/altair module:new --dir=billing --name=acme/billing --namespace='Acme\Billing'
 * ```
 *
 * The generated package implements `Altair\Module\Contracts\ModuleInterface`
 * and the route/entity/migration provider contracts, so a host app wires it in
 * with a single line in `config/modules.php`.
 */
#[Command(name: 'module:new', description: 'Scaffold a pluggable Univeros module package.')]
final readonly class MakeModuleCommand
{
    public function __construct(
        private SkeletonGenerator $generator = new SkeletonGenerator(),
    ) {}

    public function __invoke(
        #[Option(description: 'Target directory (default: current directory).')]
        ?string $dir = null,
        #[Option(description: 'Composer package name for the module, e.g. acme/user-management.')]
        string $name = 'vendor/module',
        #[Option(description: 'Root namespace (default: derived from the package name).')]
        ?string $namespace = null,
        #[Option(description: 'Overwrite a non-empty target directory.')]
        bool $force = false,
    ): int {
        $namespace ??= $this->deriveNamespace($name);
        $target = $dir ?? (getcwd() ?: '.');

        try {
            $created = $this->generator->generate(
                $target,
                skeletonDir: SkeletonGenerator::moduleSkeletonPath(),
                namespace: $namespace,
                projectName: $name,
                force: $force,
                placeholderNamespace: 'VendorModule',
                placeholderPackageName: 'vendor/module',
            );
        } catch (BootstrapException $bootstrapException) {
            fwrite(STDERR, $bootstrapException->getMessage() . "\n");

            return 1;
        }

        echo \sprintf(
            "Created %d files in %s (%s -> namespace %s).\n",
            \count($created),
            $target,
            $name,
            $namespace,
        );

        $this->printNextSteps($target, $namespace);

        return 0;
    }

    /**
     * Derive a PSR-4 root namespace from a composer package name:
     * `acme/user-management` -> `Acme\UserManagement`.
     */
    private function deriveNamespace(string $name): string
    {
        $segments = [];
        foreach (explode('/', $name) as $part) {
            $studly = $this->studly($part);
            if ($studly !== '') {
                $segments[] = $studly;
            }
        }

        return $segments === [] ? 'VendorModule' : implode('\\', $segments);
    }

    private function studly(string $value): string
    {
        $value = str_replace(['-', '_', '.'], ' ', $value);

        return str_replace(' ', '', ucwords($value));
    }

    private function printNextSteps(string $target, string $namespace): void
    {
        echo "\nNext steps:\n";
        echo \sprintf("  cd %s\n", $target);
        echo "  composer install\n";
        echo "  vendor/bin/phpunit\n";
        echo "\nRegister it in your host app's config/modules.php:\n";
        echo \sprintf("  new %s\\Module(),\n", $namespace);
    }
}

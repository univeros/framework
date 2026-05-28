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
use Altair\Bootstrap\Profile\PresetRegistry;
use Altair\Bootstrap\SkeletonGenerator;
use Altair\Bootstrap\Step\GenerateEnvStep;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;

use const STDERR;

/**
 * `bin/altair new` — materialise a runnable Altair API from the skeleton.
 *
 * ```bash
 * bin/altair new --dir=my-api --preset=standard
 * bin/altair new --dir=my-api --preset=minimal --no-interaction
 * bin/altair new --dir=my-api --name=acme/api --namespace=Acme --force
 * ```
 *
 * The generated project boots, serves `GET /ping`, and has the spec-driven
 * toolchain wired. Composer install / migrate / serve are printed as next
 * steps (they need network and a chosen runtime, so they are not run here).
 */
#[Command(name: 'new', description: 'Bootstrap a runnable Altair project from the skeleton.')]
final readonly class NewCommand
{
    public function __construct(
        private SkeletonGenerator $generator = new SkeletonGenerator(),
        private PresetRegistry $presets = new PresetRegistry(),
        private GenerateEnvStep $env = new GenerateEnvStep(),
    ) {}

    public function __invoke(
        #[Option(description: 'Target directory (default: current directory).')]
        ?string $dir = null,
        #[Option(description: 'Preset: minimal, standard or full.')]
        string $preset = 'standard',
        #[Option(description: 'Composer package name for the generated project.')]
        string $name = 'vendor/app',
        #[Option(description: 'Root namespace for the generated project.')]
        string $namespace = 'App',
        #[Option(description: 'Overwrite a non-empty target directory.')]
        bool $force = false,
    ): int {
        if (!$this->presets->has($preset)) {
            fwrite(STDERR, \sprintf(
                "Unknown preset '%s'. Available: %s.\n",
                $preset,
                implode(', ', $this->presets->names()),
            ));

            return 2;
        }

        $profile = $this->presets->get($preset);
        $target = $dir ?? (getcwd() ?: '.');

        try {
            $created = $this->generator->generate($target, null, $namespace, $name, $force);
            $this->env->run($target, $profile);
        } catch (BootstrapException $bootstrapException) {
            fwrite(STDERR, $bootstrapException->getMessage() . "\n");

            return 1;
        }

        echo \sprintf(
            "Created %d files in %s (preset: %s, orm: %s, queue: %s).\n",
            \count($created),
            $target,
            $profile->name(),
            $profile->orm(),
            $profile->queue(),
        );

        $this->printNextSteps($target);

        return 0;
    }

    private function printNextSteps(string $target): void
    {
        echo "\nNext steps:\n";
        echo \sprintf("  cd %s\n", $target);
        echo "  composer install\n";
        echo "  composer serve            # http://localhost:8080\n";
        echo "  curl localhost:8080/ping  # {\"message\":\"ok\",...}\n";
        echo "\nBuild an endpoint:\n";
        echo "  vendor/bin/altair spec:scaffold api/your-endpoint.yaml\n";
        echo "\nDrive it from an MCP client (Claude Desktop, Cursor):\n";
        echo "  add an \"altair\" server running: php vendor/bin/altair mcp serve\n";
    }
}

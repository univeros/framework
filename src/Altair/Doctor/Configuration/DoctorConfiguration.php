<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Doctor\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Doctor\Check\ComposerDepsCheck;
use Altair\Doctor\Check\ContainerBootsCheck;
use Altair\Doctor\Check\ContainerResolvesCheck;
use Altair\Doctor\Check\CsCleanCheck;
use Altair\Doctor\Check\DatabaseReachableCheck;
use Altair\Doctor\Check\DeterminismCheck;
use Altair\Doctor\Check\ExtensionsLoadedCheck;
use Altair\Doctor\Check\ManifestsCurrentCheck;
use Altair\Doctor\Check\MigrationsPendingCheck;
use Altair\Doctor\Check\OpenApiValidCheck;
use Altair\Doctor\Check\PhpstanCleanCheck;
use Altair\Doctor\Check\PhpVersionCheck;
use Altair\Doctor\Check\SpecDriftCheck;
use Altair\Doctor\Check\TestsPassingCheck;
use Altair\Doctor\CheckRegistry;
use Altair\Doctor\Contracts\ProcessRunnerInterface;
use Altair\Doctor\Doctor;
use Altair\Doctor\Output\RendererRegistry;
use Altair\Doctor\Process\ShellProcessRunner;
use Closure;

use const DIRECTORY_SEPARATOR;

use Override;

/**
 * Wires the doctor runner, the default check set, the process runner, and
 * the renderer registry into the Container.
 *
 * The PHP floor and required `ext-*` list are read from the project's
 * `composer.json` so `php_version` / `extensions_loaded` reflect the
 * project's own declared requirements. Host-app checks (container boot,
 * critical bindings, database reachability) are opt-in via constructor
 * arguments — without those hooks they report `skipped` instead of false
 * positives. Hosts add their own checks by `extend()`-ing
 * {@see CheckRegistry} after this Configuration runs.
 */
final readonly class DoctorConfiguration implements ConfigurationInterface
{
    /**
     * @param (Closure(): mixed)|null $appBooter        boot callable verifying the host Container can be constructed
     * @param list<class-string>      $criticalBindings PSR-11 ids the host considers must-resolve
     * @param (Closure(): bool)|null  $databaseProbe    closure returning true when the DB is reachable
     */
    public function __construct(
        private ?string $projectRoot = null,
        private ?Closure $appBooter = null,
        private array $criticalBindings = [],
        private ?Closure $databaseProbe = null,
    ) {}

    #[Override]
    public function apply(Container $container): void
    {
        $projectRoot = $this->projectRoot ?? (getcwd() ?: '.');
        $runner = new ShellProcessRunner();

        [$phpFloor, $extensions] = $this->readRequirements($projectRoot);

        $registry = new CheckRegistry([
            new PhpVersionCheck($phpFloor),
            new ExtensionsLoadedCheck($extensions),
            new ComposerDepsCheck($runner, $projectRoot),
            new ContainerBootsCheck($this->appBooter),
            new ContainerResolvesCheck($container, $this->criticalBindings),
            new DatabaseReachableCheck($this->databaseProbe),
            new MigrationsPendingCheck($runner, $projectRoot),
            new SpecDriftCheck($runner, $projectRoot),
            new OpenApiValidCheck($runner, $projectRoot),
            new ManifestsCurrentCheck($runner, $projectRoot),
            new CsCleanCheck($runner, $projectRoot),
            new PhpstanCleanCheck($runner, $projectRoot),
            new TestsPassingCheck($runner, $projectRoot),
            new DeterminismCheck($runner, $projectRoot),
        ]);

        $container->factory(ProcessRunnerInterface::class, static fn(): ProcessRunnerInterface => $runner)->shared();
        $container->factory(CheckRegistry::class, static fn(): CheckRegistry => $registry)->shared();
        $container->factory(Doctor::class, static fn(): Doctor => new Doctor($registry))->shared();
        $container->factory(RendererRegistry::class, static fn(): RendererRegistry => RendererRegistry::default())->shared();
    }

    /**
     * Parse the PHP floor (e.g. `8.3` from `">=8.3"`) and the `ext-*` names
     * from the project's composer.json `require` block. Falls back to the
     * running PHP's major.minor and no extensions when absent/unreadable.
     *
     * @return array{0: string, 1: list<string>}
     */
    private function readRequirements(string $projectRoot): array
    {
        $path = $projectRoot . DIRECTORY_SEPARATOR . 'composer.json';
        $fallbackFloor = \sprintf('%d.%d', PHP_MAJOR_VERSION, PHP_MINOR_VERSION);

        if (!is_file($path)) {
            return [$fallbackFloor, []];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return [$fallbackFloor, []];
        }

        $decoded = json_decode($raw, true);
        $require = \is_array($decoded) && isset($decoded['require']) && \is_array($decoded['require'])
            ? $decoded['require']
            : [];

        $floor = $fallbackFloor;
        $extensions = [];

        foreach ($require as $package => $constraint) {
            $package = (string) $package;
            if ($package === 'php' && \is_string($constraint)) {
                $floor = $this->normalizeVersion($constraint, $fallbackFloor);

                continue;
            }

            if (str_starts_with($package, 'ext-')) {
                $extensions[] = substr($package, 4);
            }
        }

        sort($extensions, SORT_STRING);

        return [$floor, $extensions];
    }

    private function normalizeVersion(string $constraint, string $fallback): string
    {
        if (preg_match('/(\d+\.\d+(?:\.\d+)?)/', $constraint, $matches) === 1) {
            return $matches[1];
        }

        return $fallback;
    }
}

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
use Altair\Doctor\Check\CsCleanCheck;
use Altair\Doctor\Check\DeterminismCheck;
use Altair\Doctor\Check\ExtensionsLoadedCheck;
use Altair\Doctor\Check\ManifestsCurrentCheck;
use Altair\Doctor\Check\PhpstanCleanCheck;
use Altair\Doctor\Check\PhpVersionCheck;
use Altair\Doctor\CheckRegistry;
use Altair\Doctor\Contracts\ProcessRunnerInterface;
use Altair\Doctor\Doctor;
use Altair\Doctor\Output\RendererRegistry;
use Altair\Doctor\Process\ShellProcessRunner;

use const DIRECTORY_SEPARATOR;

use Override;

/**
 * Wires the doctor runner, the default check set, the process runner, and
 * the renderer registry into the Container.
 *
 * The PHP floor and required `ext-*` list are read from the project's
 * `composer.json` so `php_version` / `extensions_loaded` reflect the
 * project's own declared requirements. Hosts add their own checks by
 * `prepare()`-ing {@see CheckRegistry} after this Configuration runs.
 */
final readonly class DoctorConfiguration implements ConfigurationInterface
{
    public function __construct(
        private ?string $projectRoot = null,
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
            new ManifestsCurrentCheck($runner, $projectRoot),
            new CsCleanCheck($runner, $projectRoot),
            new PhpstanCleanCheck($runner, $projectRoot),
            new DeterminismCheck($runner, $projectRoot),
        ]);

        $container
            ->delegate(ProcessRunnerInterface::class, static fn(): ProcessRunnerInterface => $runner)
            ->share(ProcessRunnerInterface::class)

            ->delegate(CheckRegistry::class, static fn(): CheckRegistry => $registry)
            ->share(CheckRegistry::class)

            ->delegate(Doctor::class, static fn(): Doctor => new Doctor($registry))
            ->share(Doctor::class)

            ->delegate(RendererRegistry::class, static fn(): RendererRegistry => RendererRegistry::default())
            ->share(RendererRegistry::class);
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

        return [$floor, array_values($extensions)];
    }

    private function normalizeVersion(string $constraint, string $fallback): string
    {
        if (preg_match('/(\d+\.\d+(?:\.\d+)?)/', $constraint, $matches) === 1) {
            return $matches[1];
        }

        return $fallback;
    }
}

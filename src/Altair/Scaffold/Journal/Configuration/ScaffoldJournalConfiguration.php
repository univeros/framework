<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Journal\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Support\Env;
use Altair\Container\Container;
use Altair\Scaffold\Journal\Differ\FileDiffer;
use Altair\Scaffold\Journal\Journal;
use Altair\Scaffold\Journal\Storage\FilesystemStorage;

use const DIRECTORY_SEPARATOR;

use Override;

/**
 * Wires the scaffold journal into the Altair Container.
 *
 * | Variable                    | Default       | Purpose                                    |
 * |-----------------------------|---------------|--------------------------------------------|
 * | ALTAIR_JOURNAL_ENABLED      | `true`        | Set `false` to skip binding the Journal.   |
 * | ALTAIR_JOURNAL_DIR          | `.altair`     | Base directory (relative to project root). |
 * | ALTAIR_JOURNAL_SUBDIR       | `journal`     | Journal subdirectory.                      |
 *
 * The `ScaffoldCommand` resolves `Journal` and `RecorderInterface` as
 * optional constructor dependencies — both are bound as nullable so
 * minimal hosts can run scaffolding without applying this Configuration.
 */
final readonly class ScaffoldJournalConfiguration implements ConfigurationInterface
{
    public function __construct(
        private ?string $projectRoot = null,
    ) {}

    #[Override]
    public function apply(Container $container): void
    {
        $projectRoot = $this->projectRoot ?? (getcwd() ?: '.');

        $container->factory(
            FileDiffer::class,
            static fn(): FileDiffer => new FileDiffer(),
        )->shared();

        $container->factory(
            FilesystemStorage::class,
            static function (Env $env) use ($projectRoot): FilesystemStorage {
                $base = (string) $env->get('ALTAIR_JOURNAL_DIR', '.altair');
                $sub = (string) $env->get('ALTAIR_JOURNAL_SUBDIR', 'journal');

                return new FilesystemStorage(
                    $projectRoot . DIRECTORY_SEPARATOR . rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $sub,
                );
            },
        )->shared();

        $container->factory(
            Journal::class,
            static fn(FilesystemStorage $storage): Journal => new Journal($storage, $projectRoot),
        )->shared();
    }
}

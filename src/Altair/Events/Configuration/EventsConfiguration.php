<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Support\Env;
use Altair\Container\Container;
use Altair\Events\Contracts\EventStorageInterface;
use Altair\Events\Contracts\RecorderInterface;
use Altair\Events\NullRecorder;
use Altair\Events\Reader;
use Altair\Events\Recorder;
use Altair\Events\Scrubber;
use Altair\Events\Storage\CheckpointStorage;
use Altair\Events\Storage\JsonlStorage;
use Altair\Events\Storage\SnapshotStorage;
use Override;

/**
 * Wires the event-log primitives into the Altair Container.
 *
 * - {@see EventsSettings} is parsed once from environment variables
 * - {@see RecorderInterface} resolves to either {@see Recorder} (default)
 *   or {@see NullRecorder} when ALTAIR_EVENTS_ENABLED=false
 * - {@see Reader}, snapshot/checkpoint storage are bound as shared
 *   services so the CLI commands can pull them straight from the
 *   container
 */
final readonly class EventsConfiguration implements ConfigurationInterface
{
    public function __construct(
        private ?string $projectRoot = null,
    ) {}

    #[Override]
    public function apply(Container $container): void
    {
        $projectRoot = $this->projectRoot;

        $container
            ->delegate(
                EventsSettings::class,
                static fn(Env $env): EventsSettings => EventsSettings::fromEnv($env, $projectRoot),
            )
            ->share(EventsSettings::class)

            ->delegate(
                Scrubber::class,
                static fn(EventsSettings $settings): Scrubber => (new Scrubber())->withSecrets($settings->extraSecretFlags),
            )
            ->share(Scrubber::class)

            ->delegate(
                JsonlStorage::class,
                static fn(EventsSettings $settings): JsonlStorage => new JsonlStorage($settings->logPath()),
            )
            ->share(JsonlStorage::class)
            ->alias(EventStorageInterface::class, JsonlStorage::class)

            ->delegate(
                SnapshotStorage::class,
                static fn(EventsSettings $settings): SnapshotStorage => new SnapshotStorage($settings->snapshotsPath()),
            )
            ->share(SnapshotStorage::class)

            ->delegate(
                CheckpointStorage::class,
                static fn(EventsSettings $settings): CheckpointStorage => new CheckpointStorage($settings->checkpointsPath()),
            )
            ->share(CheckpointStorage::class)

            ->delegate(
                Reader::class,
                static fn(EventStorageInterface $storage): Reader => new Reader($storage),
            )
            ->share(Reader::class)

            ->delegate(
                RecorderInterface::class,
                static fn(
                    EventsSettings $settings,
                    EventStorageInterface $storage,
                    Scrubber $scrubber,
                ): RecorderInterface => $settings->enabled
                    ? new Recorder($storage, $scrubber)
                    : new NullRecorder(),
            )
            ->share(RecorderInterface::class);
    }
}

<?php

declare(strict_types=1);

namespace Altair\Tests\Events\Configuration;

use Altair\Configuration\Support\Env;
use Altair\Container\Container;
use Altair\Events\Configuration\EventsConfiguration;
use Altair\Events\Configuration\EventsSettings;
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventsConfiguration::class)]
class EventsConfigurationTest extends TestCase
{
    private string $tmpRoot;

    /** @var list<string> */
    private array $appliedKeys = [];

    #[Override]
    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/altair-events-cfg-' . bin2hex(random_bytes(4));
        @mkdir($this->tmpRoot, 0775, true);
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->appliedKeys as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }

        $this->appliedKeys = [];

        $this->rrmdir($this->tmpRoot);
    }

    public function testWiresFullDependencyGraph(): void
    {
        $container = $this->bootContainer();

        $this->assertInstanceOf(EventsSettings::class, $container->make(EventsSettings::class));
        $this->assertInstanceOf(JsonlStorage::class, $container->make(JsonlStorage::class));
        $this->assertInstanceOf(JsonlStorage::class, $container->make(EventStorageInterface::class));
        $this->assertInstanceOf(SnapshotStorage::class, $container->make(SnapshotStorage::class));
        $this->assertInstanceOf(CheckpointStorage::class, $container->make(CheckpointStorage::class));
        $this->assertInstanceOf(Reader::class, $container->make(Reader::class));
        $this->assertInstanceOf(Scrubber::class, $container->make(Scrubber::class));
    }

    public function testRecorderIsLiveByDefault(): void
    {
        $container = $this->bootContainer();

        $this->assertInstanceOf(Recorder::class, $container->make(RecorderInterface::class));
    }

    public function testRecorderResolvesToNullWhenDisabled(): void
    {
        $this->setEnv(['ALTAIR_EVENTS_ENABLED' => 'false']);
        $container = $this->bootContainer();

        $this->assertInstanceOf(NullRecorder::class, $container->make(RecorderInterface::class));
    }

    public function testExtraSecretsAreAppliedToScrubber(): void
    {
        $this->setEnv(['ALTAIR_EVENTS_EXTRA_SECRET_FLAGS' => '--my-secret']);
        $container = $this->bootContainer();

        $scrubber = $container->make(Scrubber::class);
        $this->assertSame('foo --my-secret=***', $scrubber->scrub('foo --my-secret=zzz'));
    }

    private function bootContainer(): Container
    {
        $container = new Container();
        $container->instance(Env::class, new Env());

        (new EventsConfiguration(projectRoot: $this->tmpRoot))->apply($container);

        return $container;
    }

    /**
     * @param array<string, string> $values
     */
    private function setEnv(array $values): void
    {
        foreach ($values as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv(sprintf('%s=%s', $key, $value));
            $this->appliedKeys[] = $key;
        }
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        ) as $file) {
            $file->isDir() ? @rmdir((string) $file) : @unlink((string) $file);
        }

        @rmdir($dir);
    }
}

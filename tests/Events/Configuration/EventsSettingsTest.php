<?php

declare(strict_types=1);

namespace Altair\Tests\Events\Configuration;

use Altair\Configuration\Support\Env;
use Altair\Events\Configuration\EventsSettings;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventsSettings::class)]
class EventsSettingsTest extends TestCase
{
    /** @var list<string> */
    private array $appliedKeys = [];

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->appliedKeys as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }
        $this->appliedKeys = [];
    }

    public function testDefaultsWhenEnvIsBlank(): void
    {
        $settings = EventsSettings::fromEnv(new Env(), projectRoot: '/proj');

        $this->assertTrue($settings->enabled);
        $this->assertSame('/proj', $settings->projectRoot);
        $this->assertSame('.altair', $settings->baseDirectory);
        $this->assertSame('/proj' . \DIRECTORY_SEPARATOR . '.altair' . \DIRECTORY_SEPARATOR . 'events.jsonl', $settings->logPath());
        $this->assertSame('/proj' . \DIRECTORY_SEPARATOR . '.altair' . \DIRECTORY_SEPARATOR . 'snapshots', $settings->snapshotsPath());
        $this->assertSame('/proj' . \DIRECTORY_SEPARATOR . '.altair' . \DIRECTORY_SEPARATOR . 'checkpoints', $settings->checkpointsPath());
        $this->assertSame([], $settings->extraSecretFlags);
    }

    public function testEnabledFlagParsing(): void
    {
        $this->setEnv(['ALTAIR_EVENTS_ENABLED' => 'false']);
        $settings = EventsSettings::fromEnv(new Env(), projectRoot: '/proj');
        $this->assertFalse($settings->enabled);

        $this->setEnv(['ALTAIR_EVENTS_ENABLED' => '0']);
        $this->assertFalse(EventsSettings::fromEnv(new Env(), projectRoot: '/proj')->enabled);

        $this->setEnv(['ALTAIR_EVENTS_ENABLED' => 'on']);
        $this->assertTrue(EventsSettings::fromEnv(new Env(), projectRoot: '/proj')->enabled);
    }

    public function testExtraSecretFlagsParsed(): void
    {
        $this->setEnv(['ALTAIR_EVENTS_EXTRA_SECRET_FLAGS' => '--alpha, --bravo,--charlie']);

        $settings = EventsSettings::fromEnv(new Env(), projectRoot: '/proj');
        $this->assertSame(['--alpha', '--bravo', '--charlie'], $settings->extraSecretFlags);
    }

    public function testCustomPathOverrides(): void
    {
        $this->setEnv([
            'ALTAIR_EVENTS_DIR' => 'storage/altair',
            'ALTAIR_EVENTS_LOG_FILE' => 'audit.jsonl',
        ]);

        $settings = EventsSettings::fromEnv(new Env(), projectRoot: '/proj');
        $this->assertSame('/proj' . \DIRECTORY_SEPARATOR . 'storage/altair' . \DIRECTORY_SEPARATOR . 'audit.jsonl', $settings->logPath());
    }

    /**
     * @param array<string, string> $vars
     */
    private function setEnv(array $vars): void
    {
        foreach ($vars as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
            $this->appliedKeys[] = $key;
        }
    }
}

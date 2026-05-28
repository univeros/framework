<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Observatory\Panel;

use Altair\Container\Container;
use Altair\Introspection\Inspector\ConfigInspector;
use Altair\Observatory\Panel\ConfigPanel;
use Altair\Observatory\Panel\PanelStatus;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigPanel::class)]
final class ConfigPanelTest extends TestCase
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

    public function testIdentity(): void
    {
        $panel = new ConfigPanel(new ConfigInspector(new Container()));

        self::assertSame('config', $panel->id());
        self::assertSame('Config', $panel->label());
        self::assertSame('adjustments', $panel->icon());
    }

    public function testSnapshotMasksSecretsAndReportsKeyCount(): void
    {
        $this->setEnv([
            'OBS_DB_PASSWORD' => 'hunter2',
            'OBS_LOG_LEVEL' => 'info',
        ]);

        $snapshot = (new ConfigPanel(new ConfigInspector(new Container())))->snapshot();

        self::assertSame(PanelStatus::Ok, $snapshot->status);
        self::assertGreaterThan(0, $snapshot->metrics['keys']);
        self::assertStringContainsString('key', $snapshot->headline);

        $byKey = [];
        foreach ($snapshot->items as $item) {
            self::assertArrayHasKey('key', $item);
            self::assertArrayHasKey('value', $item);
            $byKey[(string) $item['key']] = $item['value'];
        }

        // The secret-looking key must be redacted; the raw value must never leak.
        self::assertSame('***', $byKey['OBS_DB_PASSWORD']);
        self::assertNotContains('hunter2', array_values($byKey));
        self::assertSame('info', $byKey['OBS_LOG_LEVEL']);
    }

    public function testContainerParametersAreMaskedWhenSecretLooking(): void
    {
        $container = new Container();
        $container->defineParameter('apiToken', 'abc123');

        $snapshot = (new ConfigPanel(new ConfigInspector($container)))->snapshot();

        $masked = null;
        foreach ($snapshot->items as $item) {
            if ($item['key'] === '$apiToken') {
                $masked = $item['value'];
                break;
            }
        }

        self::assertSame('***', $masked);
    }

    /**
     * @param array<string, string> $values
     */
    private function setEnv(array $values): void
    {
        foreach ($values as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv(\sprintf('%s=%s', $key, $value));
            $this->appliedKeys[] = $key;
        }
    }
}

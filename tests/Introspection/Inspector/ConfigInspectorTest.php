<?php

declare(strict_types=1);

namespace Altair\Tests\Introspection\Inspector;

use Altair\Container\Container;
use Altair\Introspection\Inspector\ConfigInspector;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigInspector::class)]
class ConfigInspectorTest extends TestCase
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

    public function testMasksSecretKeysByDefault(): void
    {
        $this->setEnv([
            'INTRO_DB_PASSWORD' => 'hunter2',
            'INTRO_API_TOKEN' => 'abc123',
            'INTRO_LOG_LEVEL' => 'info',
        ]);

        $rows = (new ConfigInspector(new Container()))->dump()->rows;
        $byKey = [];
        foreach ($rows as $row) {
            if ($row['source'] === 'env' && \in_array($row['key'], ['INTRO_DB_PASSWORD', 'INTRO_API_TOKEN', 'INTRO_LOG_LEVEL'], true)) {
                $byKey[$row['key']] = $row['value'];
            }
        }

        $this->assertSame('***', $byKey['INTRO_DB_PASSWORD']);
        $this->assertSame('***', $byKey['INTRO_API_TOKEN']);
        $this->assertSame('info', $byKey['INTRO_LOG_LEVEL']);
    }

    public function testRawDumpExposesValuesWhenMaskingDisabled(): void
    {
        $this->setEnv(['INTRO_DB_PASSWORD' => 'hunter2']);

        $rows = (new ConfigInspector(new Container()))->dump(maskSecrets: false)->rows;
        $found = null;
        foreach ($rows as $row) {
            if ($row['source'] === 'env' && $row['key'] === 'INTRO_DB_PASSWORD') {
                $found = $row['value'];
                break;
            }
        }

        $this->assertSame('hunter2', $found);
    }

    public function testIncludesContainerParameters(): void
    {
        $container = new Container();
        $container->value('appName', 'demo');

        $rows = (new ConfigInspector($container))->dump()->rows;
        $found = false;
        foreach ($rows as $row) {
            if ($row['source'] === 'container' && $row['key'] === '$appName' && $row['value'] === 'demo') {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found);
    }

    public function testExtraPatternsAreApplied(): void
    {
        $this->setEnv(['INTRO_DEMO_CUSTOM' => 'sensitive']);

        $inspector = new ConfigInspector(new Container(), extraSecretPatterns: ['CUSTOM']);
        $rows = $inspector->dump()->rows;
        $found = null;
        foreach ($rows as $row) {
            if ($row['source'] === 'env' && $row['key'] === 'INTRO_DEMO_CUSTOM') {
                $found = $row['value'];
                break;
            }
        }

        $this->assertSame('***', $found);
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
}

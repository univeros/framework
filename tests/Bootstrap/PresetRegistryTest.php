<?php

declare(strict_types=1);

namespace Altair\Tests\Bootstrap;

use Altair\Bootstrap\Profile\MinimalPreset;
use Altair\Bootstrap\Profile\StandardPreset;
use Altair\Bootstrap\Profile\FullPreset;
use Altair\Bootstrap\Exception\BootstrapException;
use Altair\Bootstrap\Profile\PresetRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PresetRegistry::class)]
#[CoversClass(MinimalPreset::class)]
#[CoversClass(StandardPreset::class)]
#[CoversClass(FullPreset::class)]
final class PresetRegistryTest extends TestCase
{
    public function testDefaultsExposeMinimalStandardFull(): void
    {
        self::assertSame(['minimal', 'standard', 'full'], (new PresetRegistry())->names());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function presets(): iterable
    {
        yield 'minimal' => ['minimal', 'none', 'sync'];
        yield 'standard' => ['standard', 'cycle', 'redis'];
        yield 'full' => ['full', 'cycle', 'redis'];
    }

    #[DataProvider('presets')]
    public function testResolvesPresetChoices(string $name, string $orm, string $queue): void
    {
        $preset = (new PresetRegistry())->get($name);

        self::assertSame($name, $preset->name());
        self::assertSame($orm, $preset->orm());
        self::assertSame($queue, $preset->queue());
    }

    public function testUnknownPresetThrows(): void
    {
        $this->expectException(BootstrapException::class);
        (new PresetRegistry())->get('enterprise');
    }
}

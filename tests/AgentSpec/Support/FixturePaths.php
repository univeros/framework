<?php

declare(strict_types=1);

namespace Altair\Tests\AgentSpec\Support;

/**
 * Shared helpers that point AgentSpec scanners at the on-disk fixture package.
 */
final class FixturePaths
{
    public static function fixturesRoot(): string
    {
        return realpath(__DIR__ . '/../Fixtures') ?: __DIR__ . '/../Fixtures';
    }

    public static function sourceRoot(): string
    {
        return self::fixturesRoot();
    }

    public static function samplePackageRoot(): string
    {
        return self::fixturesRoot() . DIRECTORY_SEPARATOR . 'SamplePackage';
    }

    public static function testsRoot(): string
    {
        return self::fixturesRoot() . DIRECTORY_SEPARATOR . 'TestsRoot';
    }

    public static function monorepoRoot(): string
    {
        return realpath(__DIR__ . '/../../..') ?: __DIR__ . '/../../..';
    }
}

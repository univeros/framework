<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Support;

use PHPUnit\Framework\TestCase;

/**
 * Loads and compares golden-file snapshots.
 *
 * The first time a snapshot is missing, set ALTAIR_SCAFFOLD_UPDATE_SNAPSHOTS=1
 * in the environment to write it; subsequent runs will assert equality.
 */
final class Snapshots
{
    private const DIRECTORY = __DIR__ . '/../Snapshots';

    public static function assertMatches(TestCase $test, string $name, string $actual): void
    {
        $path = self::DIRECTORY . '/' . $name;

        if (!is_file($path)) {
            if (getenv('ALTAIR_SCAFFOLD_UPDATE_SNAPSHOTS') === '1') {
                $dir = \dirname($path);
                if (!is_dir($dir)) {
                    mkdir($dir, 0o755, true);
                }
                file_put_contents($path, $actual);
                $test::markTestIncomplete(\sprintf("Wrote snapshot '%s'. Re-run the test.", $name));
                return;
            }

            $test::fail(\sprintf(
                "Snapshot '%s' is missing. Re-run with ALTAIR_SCAFFOLD_UPDATE_SNAPSHOTS=1 to create it.",
                $path,
            ));
        }

        $expected = (string) file_get_contents($path);

        if (getenv('ALTAIR_SCAFFOLD_UPDATE_SNAPSHOTS') === '1' && $expected !== $actual) {
            file_put_contents($path, $actual);
            $test::markTestIncomplete(\sprintf("Updated snapshot '%s'. Re-run the test.", $name));
            return;
        }

        $test::assertSame($expected, $actual, \sprintf("Output does not match snapshot '%s'.", $name));
    }
}

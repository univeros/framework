<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\AgentSpec\Determinism;

use Altair\AgentSpec\Cli\ManifestGenerateCommand;
use Altair\Tests\Determinism\TwiceHarness;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The #74 acceptance gate for the manifest emitter: running it twice from
 * the same source tree must produce byte-identical files. If this fails,
 * something in the manifest pipeline is iterating an unsorted map / leaking
 * a wall-clock timestamp / depending on inode-order FS traversal.
 */
#[CoversClass(ManifestGenerateCommand::class)]
final class ManifestDeterminismTest extends TestCase
{
    private TwiceHarness $harness;

    protected function setUp(): void
    {
        $this->harness = new TwiceHarness('altair-determinism-manifest');
    }

    protected function tearDown(): void
    {
        $this->harness->cleanup();
    }

    public function testManifestGeneratorProducesByteIdenticalOutputAcrossRuns(): void
    {
        $command = new ManifestGenerateCommand();
        [$first, $second] = $this->harness->pair(function (string $outputDir) use ($command): void {
            ob_start();
            $command(output: $outputDir);
            ob_end_clean();
        });

        $this->harness->assertTreesByteEqual($first, $second);
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Scaffold\Determinism;

use Altair\Scaffold\Cli\EmitOpenApiCommand;
use Altair\Tests\Determinism\TwiceHarness;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * #74 acceptance: the OpenAPI fragment-merger must produce byte-identical
 * output across runs. With multiple fragments under `docs/openapi/`, this
 * exercises both the fragment-ordering (FS-iteration order is inode-defined,
 * so the merger must sort) and the YAML dumper's stability.
 */
#[CoversClass(EmitOpenApiCommand::class)]
final class EmitOpenApiDeterminismTest extends TestCase
{
    private TwiceHarness $harness;

    private string $fragmentsDir = '';

    protected function setUp(): void
    {
        $this->harness = new TwiceHarness('altair-determinism-openapi');

        // Two fragments — exercises the cross-fragment merge ordering, not
        // just the single-document emit. The fragments are dropped into the
        // temp dir in a deliberately-unsorted order: a deterministic emitter
        // must produce the same merged YAML regardless.
        $this->fragmentsDir = sys_get_temp_dir() . '/altair-openapi-frags-' . bin2hex(random_bytes(4));
        mkdir($this->fragmentsDir, 0o755, true);
        file_put_contents($this->fragmentsDir . '/zeta.yaml', $this->fragment('GET', '/zeta'));
        file_put_contents($this->fragmentsDir . '/alpha.yaml', $this->fragment('GET', '/alpha'));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->fragmentsDir . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->fragmentsDir);
        $this->harness->cleanup();
    }

    public function testOpenApiEmitterIsByteDeterministicAcrossRuns(): void
    {
        $command = new EmitOpenApiCommand();
        $fragmentsDir = $this->fragmentsDir;

        [$first, $second] = $this->harness->pair(function (string $outDir) use ($command, $fragmentsDir): void {
            ob_start();
            $command(fragmentsDir: $fragmentsDir, outFile: $outDir . '/openapi.yaml');
            ob_end_clean();
        });

        $this->harness->assertFilesByteEqual($first . '/openapi.yaml', $second . '/openapi.yaml');
    }

    private function fragment(string $method, string $path): string
    {
        return <<<YAML
            openapi: 3.1.0
            info:
              title: Frag
              version: 1.0.0
            paths:
              "{$path}":
                {$method}:
                  summary: Frag handler for {$path}
                  responses:
                    "200":
                      description: ok
            YAML;
    }
}

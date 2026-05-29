<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Scaffold\Determinism;

use Altair\Scaffold\Cli\EmitSdkCommand;
use Altair\Tests\Determinism\TwiceHarness;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * #74 acceptance: every SDK emitter must produce byte-identical output across
 * runs given the same OpenAPI input. A drift here would mean the emitter is
 * iterating an unordered schema map or stamping a timestamp into the output.
 */
#[CoversClass(EmitSdkCommand::class)]
final class SdkEmitterDeterminismTest extends TestCase
{
    private TwiceHarness $harness;

    private string $openapiPath;

    protected function setUp(): void
    {
        $this->harness = new TwiceHarness('altair-determinism-sdk');
        $this->openapiPath = __DIR__ . '/../Sdk/Fixtures/users-api.yaml';
    }

    protected function tearDown(): void
    {
        $this->harness->cleanup();
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function languages(): iterable
    {
        yield 'typescript' => ['typescript'];
        yield 'python' => ['python'];
    }

    #[DataProvider('languages')]
    public function testSdkEmitterIsByteDeterministicAcrossRuns(string $language): void
    {
        $openapi = $this->openapiPath;
        $command = new EmitSdkCommand();

        [$first, $second] = $this->harness->pair(function (string $out) use ($command, $openapi, $language): void {
            ob_start();
            $command(language: $language, openapi: $openapi, out: $out, multiFile: true);
            ob_end_clean();
        });

        $this->harness->assertTreesByteEqual($first, $second);
    }
}

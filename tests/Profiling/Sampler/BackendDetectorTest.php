<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Profiling\Sampler;

use Altair\Profiling\Exception\SamplerUnavailableException;
use Altair\Profiling\Sampler\BackendDetector;
use Altair\Profiling\Sampler\ExcimerSampler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BackendDetector::class)]
#[CoversClass(ExcimerSampler::class)]
#[CoversClass(SamplerUnavailableException::class)]
final class BackendDetectorTest extends TestCase
{
    public function testDetectThrowsWhenNoSamplingBackendIsLoaded(): void
    {
        if (ExcimerSampler::available()) {
            self::markTestSkipped('ext-excimer is loaded; this test asserts the no-backend path.');
        }

        self::expectException(SamplerUnavailableException::class);
        self::expectExceptionMessageMatches('/ext-excimer/');

        (new BackendDetector())->detect();
    }

    public function testDetectReturnsExcimerSamplerWhenExtensionIsLoaded(): void
    {
        if (!ExcimerSampler::available()) {
            self::markTestSkipped('ext-excimer is not loaded.');
        }

        $sampler = (new BackendDetector())->detect(2_000);

        self::assertInstanceOf(ExcimerSampler::class, $sampler);
        self::assertSame('excimer', $sampler->backend());
        self::assertSame(2_000, $sampler->periodUs());
    }

    public function testIsAvailableMirrorsExcimerExtensionPresence(): void
    {
        self::assertSame(ExcimerSampler::available(), (new BackendDetector())->isAvailable());
    }
}

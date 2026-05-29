<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Sampler;

use Altair\Profiling\Exception\SamplerUnavailableException;
use Altair\Profiling\Sampler\Contracts\SamplerInterface;

/**
 * Picks the best loaded sampling backend.
 *
 * Order of preference: `ext-excimer` (low overhead, statistical sampling),
 * then a future `ext-xdebug` adapter. When neither is present the detector
 * throws {@see SamplerUnavailableException} with install hints so a CLI user
 * or an MCP client sees what to do next. (`xdebug` adapter is a documented
 * follow-up; this v1 ships excimer only.)
 */
final class BackendDetector
{
    public function detect(int $periodUs = ExcimerSampler::DEFAULT_PERIOD_US): SamplerInterface
    {
        if (ExcimerSampler::available()) {
            return new ExcimerSampler($periodUs);
        }

        throw SamplerUnavailableException::noBackend();
    }

    public function isAvailable(): bool
    {
        return ExcimerSampler::available();
    }
}

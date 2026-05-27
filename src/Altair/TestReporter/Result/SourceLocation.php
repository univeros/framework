<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\TestReporter\Result;

/**
 * Best-effort pointer to the production-source location the agent
 * should jump to when a test fails.
 */
final readonly class SourceLocation
{
    public function __construct(
        public string $file,
        public ?string $method = null,
        public ?string $lines = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = ['file' => $this->file];
        if ($this->method !== null) {
            $out['method'] = $this->method;
        }

        if ($this->lines !== null) {
            $out['lines'] = $this->lines;
        }

        return $out;
    }
}

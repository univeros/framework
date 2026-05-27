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
 * One frame of an exception stack trace. Project-relative paths so
 * the report is portable across machines.
 */
final readonly class StackFrame
{
    public function __construct(
        public string $file,
        public int $line,
        public ?string $function = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = ['file' => $this->file, 'line' => $this->line];
        if ($this->function !== null) {
            $out['function'] = $this->function;
        }

        return $out;
    }
}

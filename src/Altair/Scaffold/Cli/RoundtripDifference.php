<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Cli;

/**
 * One semantic difference between the source OpenAPI document and the
 * round-tripped output. `pointer` is a JSON pointer locating the drift
 * inside the document; `kind` discriminates so agents can branch without
 * regexing the message.
 */
final readonly class RoundtripDifference
{
    public const string KIND_MISSING_OPERATION = 'missing_operation';

    public const string KIND_EXTRA_OPERATION = 'extra_operation';

    public const string KIND_SUMMARY_DRIFT = 'summary_drift';

    public const string KIND_EXTENSION_DRIFT = 'extension_drift';

    public const string KIND_STATUS_DRIFT = 'status_drift';

    public function __construct(
        public string $kind,
        public string $pointer,
        public mixed $expected,
        public mixed $actual,
        public string $message,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'pointer' => $this->pointer,
            'expected' => $this->expected,
            'actual' => $this->actual,
            'message' => $this->message,
        ];
    }
}

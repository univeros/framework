<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Safety;

/**
 * One safety observation about a proposed migration, raised by a check that
 * queried the live database.
 */
final readonly class SafetyFinding
{
    public function __construct(
        public Severity $severity,
        public string $message,
        public string $check,
    ) {}

    public static function info(string $message, string $check): self
    {
        return new self(Severity::Info, $message, $check);
    }

    public static function warn(string $message, string $check): self
    {
        return new self(Severity::Warn, $message, $check);
    }

    public static function error(string $message, string $check): self
    {
        return new self(Severity::Error, $message, $check);
    }

    /**
     * @return array{severity: string, message: string, check: string}
     */
    public function toArray(): array
    {
        return [
            'severity' => $this->severity->value,
            'message' => $this->message,
            'check' => $this->check,
        ];
    }
}

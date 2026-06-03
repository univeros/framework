<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Logging\Configuration;

use Altair\Configuration\Support\Env;

/**
 * Immutable logging configuration parsed once from the environment.
 *
 *  - LOG_CHANNEL  the Monolog channel name              (default: app)
 *  - LOG_LEVEL    minimum PSR-3 level to record         (default: debug)
 *  - LOG_PATH     stream/file the handler writes to     (default: php://stderr)
 *  - LOG_FORMAT   `json` (newline-delimited) or `line`  (default: json)
 *
 * The JSON default keeps application logs machine-parseable, consistent with
 * the rest of the framework's agent-facing output; `line` is the human-friendly
 * option for local development.
 */
final readonly class LoggingSettings
{
    public function __construct(
        public string $channel,
        public string $level,
        public string $path,
        public string $format,
    ) {}

    public static function fromEnv(Env $env): self
    {
        return new self(
            channel: self::asString($env->get('LOG_CHANNEL'), 'app'),
            level: self::asString($env->get('LOG_LEVEL'), 'debug'),
            path: self::asString($env->get('LOG_PATH'), 'php://stderr'),
            format: self::normalizeFormat($env->get('LOG_FORMAT')),
        );
    }

    public function isJson(): bool
    {
        return $this->format === 'json';
    }

    private static function asString(mixed $value, string $default): string
    {
        return \is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    /**
     * Anything that is not an explicit `line` resolves to `json` — the safe,
     * agent-friendly default.
     */
    private static function normalizeFormat(mixed $value): string
    {
        return \is_string($value) && strtolower(trim($value)) === 'line' ? 'line' : 'json';
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events;

/**
 * Redact secret values from a command string before it lands in the log.
 *
 * The log is local and trusted, but command lines routinely carry secrets
 * by accident (env-style flags, copy-pasted dev passwords). Scrubbing
 * keeps "tail the log to debug" from being the way credentials leak.
 *
 * Supported flag forms (case-insensitive on the flag name):
 *   --password=hunter2          → --password=***
 *   --password hunter2          → --password ***
 *   -p hunter2                  → -p ***          (only for short flags
 *                                                   explicitly listed)
 *
 * The default list covers the common offenders; host applications add
 * more via {@see withSecrets()}.
 */
final readonly class Scrubber
{
    public const string REDACTED = '***';

    /** @var list<string> */
    public const array DEFAULT_SECRETS = [
        '--password',
        '--passwd',
        '--pass',
        '--token',
        '--api-key',
        '--api_key',
        '--apikey',
        '--secret',
        '--secret-key',
        '--auth',
        '--authorization',
        '--bearer',
        '--access-key',
        '--access-token',
        '--client-secret',
        '--private-key',
        '--db-password',
    ];

    /**
     * @param list<string> $secrets Flag names to redact (must include leading dashes).
     */
    public function __construct(
        private array $secrets = self::DEFAULT_SECRETS,
    ) {}

    /**
     * @param list<string> $extra Additional secret flags to recognise.
     */
    public function withSecrets(array $extra): self
    {
        return new self(array_values(array_unique([...$this->secrets, ...$extra])));
    }

    public function scrub(string $command): string
    {
        $result = $command;
        foreach ($this->secrets as $flag) {
            $quoted = preg_quote($flag, '/');

            // --flag=value
            $result = (string) preg_replace(
                '/(' . $quoted . ')=\S+/i',
                '$1=' . self::REDACTED,
                $result,
            );

            // --flag value  (next whitespace-delimited token)
            $result = (string) preg_replace(
                '/(' . $quoted . ')(\s+)\S+/i',
                '$1$2' . self::REDACTED,
                $result,
            );
        }

        return $result;
    }
}

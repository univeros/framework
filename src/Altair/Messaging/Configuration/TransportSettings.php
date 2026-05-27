<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Messaging\Configuration;

use Altair\Configuration\Support\Env;
use Altair\Messaging\Exception\InvalidArgumentException;

/**
 * Immutable transport configuration parsed from environment variables.
 *
 * Conventions:
 *   MESSENGER_TRANSPORT_DEFAULT=redis://localhost:6379/messages
 *   MESSENGER_TRANSPORT_HIGH=doctrine://default?queue_name=high
 *   MESSENGER_TRANSPORT_FAILED=doctrine://default?queue_name=failed
 *   MESSENGER_FAILURE_TRANSPORT=failed
 *   MESSENGER_ROUTING="App\Messages\SendWelcomeEmail:default,App\Messages\GenerateReport:high"
 */
final readonly class TransportSettings
{
    public const string ENV_PREFIX = 'MESSENGER_TRANSPORT_';

    public const string ENV_FAILURE_TRANSPORT = 'MESSENGER_FAILURE_TRANSPORT';

    public const string ENV_ROUTING = 'MESSENGER_ROUTING';

    public const string DEFAULT_TRANSPORT = 'default';

    /**
     * @param array<string, string>                   $dsns    transport name => DSN
     * @param array<class-string, list<string>>       $routing message FQCN => list of transport names
     */
    public function __construct(
        public array $dsns,
        public array $routing = [],
        public ?string $failureTransport = null,
    ) {}

    public static function fromEnv(Env $env): self
    {
        $dsns = self::collectTransportDsns($env);
        $failure = $env->get(self::ENV_FAILURE_TRANSPORT);
        $routing = self::parseRouting((string) ($env->get(self::ENV_ROUTING) ?? ''));

        if ($failure !== null && $failure !== '' && !isset($dsns[$failure])) {
            throw new InvalidArgumentException(\sprintf(
                "Failure transport '%s' is not defined; declare %s%s=...",
                (string) $failure,
                self::ENV_PREFIX,
                strtoupper((string) $failure),
            ));
        }

        return new self(
            dsns: $dsns,
            routing: $routing,
            failureTransport: $failure === null || $failure === '' ? null : (string) $failure,
        );
    }

    public function hasTransport(string $name): bool
    {
        return isset($this->dsns[$name]);
    }

    public function dsn(string $name): string
    {
        if (!$this->hasTransport($name)) {
            throw new InvalidArgumentException(\sprintf("Transport '%s' is not configured.", $name));
        }

        return $this->dsns[$name];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->dsns);
    }

    /**
     * @return array<string, string>
     */
    private static function collectTransportDsns(Env $env): array
    {
        $candidates = [];

        foreach ($_ENV as $name => $value) {
            if (str_starts_with((string) $name, self::ENV_PREFIX)) {
                $candidates[(string) $name] = true;
            }
        }

        foreach ($_SERVER as $name => $value) {
            if (str_starts_with((string) $name, self::ENV_PREFIX)) {
                $candidates[(string) $name] = true;
            }
        }

        foreach (self::scanProcessEnv() as $name) {
            if (str_starts_with($name, self::ENV_PREFIX)) {
                $candidates[$name] = true;
            }
        }

        $dsns = [];
        foreach (array_keys($candidates) as $envName) {
            $value = $env->get($envName);
            if (!\is_string($value)) {
                continue;
            }

            if ($value === '') {
                continue;
            }

            $transportName = strtolower(substr($envName, \strlen(self::ENV_PREFIX)));
            if ($transportName === '') {
                continue;
            }

            $dsns[$transportName] = $value;
        }

        return $dsns;
    }

    /**
     * @return list<string>
     */
    private static function scanProcessEnv(): array
    {
        $raw = getenv();

        return \is_array($raw) ? array_map('strval', array_keys($raw)) : [];
    }

    /**
     * @return array<class-string, list<string>>
     */
    private static function parseRouting(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $routing = [];
        foreach (explode(',', $raw) as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }

            if (!str_contains($pair, ':')) {
                throw new InvalidArgumentException(\sprintf(
                    "Malformed routing entry '%s'; expected 'Fully\\Qualified\\Message:transport[,transport...]'.",
                    $pair,
                ));
            }

            [$messageClass, $transports] = explode(':', $pair, 2);
            $messageClass = trim($messageClass);
            $transportList = array_values(array_filter(array_map('trim', explode('|', $transports))));

            if ($messageClass === '' || $transportList === []) {
                throw new InvalidArgumentException(\sprintf("Malformed routing entry '%s'.", $pair));
            }

            /** @var class-string $messageClass */
            $routing[$messageClass] = $transportList;
        }

        return $routing;
    }
}

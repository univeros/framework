<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Introspection\Inspector;

use Altair\Container\Container;
use Altair\Introspection\Result\InspectionTable;

/**
 * Dumps the merged environment + container-parameter view.
 *
 * Secret-mask defaults match the issue spec (`PASSWORD`, `SECRET`,
 * `TOKEN`, `KEY`); hosts add more via constructor injection.
 *
 * Reads env from $_ENV / $_SERVER / `getenv()` in that order so the
 * output matches what `Altair\Configuration\Support\Env` would see at
 * runtime. Walks `Container::getParameterDefinitions()` directly —
 * never triggers `make()`.
 */
final readonly class ConfigInspector
{
    /** @var list<string> Default substring patterns to flag as secrets. */
    public const array DEFAULT_SECRET_PATTERNS = [
        'PASSWORD', 'SECRET', 'TOKEN', 'KEY', 'CREDENTIAL', 'PRIVATE',
        'AUTH', 'BEARER', 'API_KEY', 'ACCESS_KEY',
    ];

    public const string REDACTED = '***';

    /**
     * @param list<string> $extraSecretPatterns Additional case-insensitive substring patterns to mask.
     */
    public function __construct(
        private Container $container,
        private array $extraSecretPatterns = [],
    ) {}

    public function dump(bool $maskSecrets = true): InspectionTable
    {
        $rows = [];
        $patterns = array_values(array_unique([...self::DEFAULT_SECRET_PATTERNS, ...$this->extraSecretPatterns]));

        foreach ($this->collectEnv() as $key => $value) {
            $rows[] = [
                'source' => 'env',
                'key' => $key,
                'value' => $this->renderValue($key, $value, $maskSecrets, $patterns),
            ];
        }

        foreach ($this->container->getParameterDefinitions() as $name => $value) {
            $rows[] = [
                'source' => 'container',
                'key' => '$' . $name,
                'value' => $this->renderValue($name, $value, $maskSecrets, $patterns),
            ];
        }

        usort($rows, static fn(array $a, array $b): int => [$a['source'], $a['key']] <=> [$b['source'], $b['key']]);

        return new InspectionTable(
            title: $maskSecrets ? 'Configuration (secrets masked)' : 'Configuration (raw)',
            columns: ['source', 'key', 'value'],
            rows: $rows,
            extras: ['masked' => $maskSecrets, 'total' => \count($rows)],
        );
    }

    /**
     * @return iterable<string, mixed>
     */
    private function collectEnv(): iterable
    {
        // De-duplicate across the three sources; $_ENV wins over $_SERVER wins over getenv().
        $merged = array_merge(
            $this->envFromGetenv(),
            \is_array($_SERVER) ? $_SERVER : [],
            \is_array($_ENV) ? $_ENV : [],
        );

        ksort($merged);
        foreach ($merged as $k => $v) {
            if (\is_string($k) && $k !== '') {
                yield $k => $v;
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function envFromGetenv(): array
    {
        $out = [];
        foreach (getenv() as $key => $value) {
            if ($key !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $patterns
     */
    private function renderValue(string $key, mixed $value, bool $maskSecrets, array $patterns): mixed
    {
        if ($maskSecrets && $this->matchesSecret($key, $patterns)) {
            return self::REDACTED;
        }

        if ($value === null) {
            return null;
        }

        if (\is_scalar($value)) {
            return $value;
        }

        if (\is_object($value)) {
            return $value::class;
        }

        return $value;
    }

    /**
     * @param list<string> $patterns
     */
    private function matchesSecret(string $key, array $patterns): bool
    {
        $upper = strtoupper($key);
        foreach ($patterns as $pattern) {
            if ($pattern !== '' && str_contains($upper, strtoupper($pattern))) {
                return true;
            }
        }

        return false;
    }
}

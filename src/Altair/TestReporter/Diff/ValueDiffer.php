<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\TestReporter\Diff;

use PHPUnit\Event\Code\ComparisonFailure;

/**
 * Renders structured diff payloads for `assertSame` / `assertEquals`
 * failures.
 *
 * Output shape is keyed by the input kind so agents can branch on it
 * without parsing the values themselves:
 *
 *   - `{"kind":"scalar","expected":...,"actual":...}`
 *   - `{"kind":"array","added":[],"removed":[],"changed":{}}`
 *   - `{"kind":"string","expected_preview":"...","actual_preview":"..."}`
 *   - `{"kind":"object","expected_class":"X","actual_class":"Y"}`
 *
 * Returns `null` when the comparison failure didn't carry a
 * comparable pair (most non-comparison assertions).
 */
final readonly class ValueDiffer
{
    public const int STRING_PREVIEW_LIMIT = 200;

    /**
     * @return array<string, mixed>|null
     */
    public function diff(?ComparisonFailure $failure): ?array
    {
        if (!$failure instanceof ComparisonFailure) {
            return null;
        }

        $expected = $failure->expected();
        $actual = $failure->actual();

        if ($expected === '' && $actual === '') {
            return null;
        }

        $expectedValue = $this->decode($expected);
        $actualValue = $this->decode($actual);

        return $this->renderDiff($expectedValue, $actualValue);
    }

    /**
     * Direct-value entry point used by callers that already hold the
     * pre-decoded expected/actual pair (e.g. {@see ValueDifferTest}).
     *
     * @return array<string, mixed>
     */
    public function renderDiff(mixed $expected, mixed $actual): array
    {
        if (\is_array($expected) && \is_array($actual)) {
            return ['kind' => 'array'] + $this->arrayDiff($expected, $actual);
        }

        if (\is_string($expected) && \is_string($actual)) {
            return [
                'kind' => 'string',
                'expected_preview' => $this->preview($expected),
                'actual_preview' => $this->preview($actual),
                'expected_length' => mb_strlen($expected),
                'actual_length' => mb_strlen($actual),
            ];
        }

        if (\is_object($expected) || \is_object($actual)) {
            return [
                'kind' => 'object',
                'expected_class' => \is_object($expected) ? $expected::class : $this->typeName($expected),
                'actual_class' => \is_object($actual) ? $actual::class : $this->typeName($actual),
                'expected_preview' => $this->preview($this->stringify($expected)),
                'actual_preview' => $this->preview($this->stringify($actual)),
            ];
        }

        return [
            'kind' => 'scalar',
            'expected' => $expected,
            'actual' => $actual,
        ];
    }

    /**
     * @param array<mixed> $expected
     * @param array<mixed> $actual
     *
     * @return array{ added: array<int|string, mixed>, removed: array<int|string, mixed>, changed: array<int|string, array{ expected: mixed, actual: mixed }> }
     */
    private function arrayDiff(array $expected, array $actual): array
    {
        $added = [];
        $removed = [];
        $changed = [];

        foreach ($actual as $k => $v) {
            if (!\array_key_exists($k, $expected)) {
                $added[$k] = $v;
            } elseif ($expected[$k] !== $v) {
                $changed[$k] = ['expected' => $expected[$k], 'actual' => $v];
            }
        }

        foreach ($expected as $k => $v) {
            if (!\array_key_exists($k, $actual)) {
                $removed[$k] = $v;
            }
        }

        return ['added' => $added, 'removed' => $removed, 'changed' => $changed];
    }

    /**
     * `ComparisonFailure::expected()` / `::actual()` return strings —
     * try to recover the original PHP value when it's serializable.
     */
    private function decode(string $raw): mixed
    {
        if ($raw === '') {
            return '';
        }

        if ($raw === 'true') {
            return true;
        }

        if ($raw === 'false') {
            return false;
        }

        if ($raw === 'null') {
            return null;
        }

        if (is_numeric($raw) && !str_contains($raw, ' ')) {
            return str_contains($raw, '.') ? (float) $raw : (int) $raw;
        }

        // Strip PHPUnit's typical single-quote wrapping: `'foo'`.
        if (\strlen($raw) >= 2 && $raw[0] === "'" && $raw[\strlen($raw) - 1] === "'") {
            return substr($raw, 1, -1);
        }

        return $raw;
    }

    private function stringify(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }

        if ($value === null) {
            return '(null)';
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        if (\is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function preview(string $value): string
    {
        if (mb_strlen($value) <= self::STRING_PREVIEW_LIMIT) {
            return $value;
        }

        return mb_substr($value, 0, self::STRING_PREVIEW_LIMIT) . '… (' . (mb_strlen($value) - self::STRING_PREVIEW_LIMIT) . ' more chars)';
    }

    private function typeName(mixed $v): string
    {
        return match (true) {
            $v === null => 'null',
            \is_bool($v) => 'bool',
            \is_int($v) => 'int',
            \is_float($v) => 'float',
            \is_string($v) => 'string',
            \is_array($v) => 'array',
            default => get_debug_type($v),
        };
    }
}

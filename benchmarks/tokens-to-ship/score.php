<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

/*
 * Tokens-to-Ship scorer.
 *
 * Aggregates a usage log (see README.md) into per-arm medians + spread, pass@1,
 * and baseline/altair comparison ratios. Writes results/results.json and prints
 * a table. Pure reporting: it never runs the agent or the acceptance suite.
 *
 *   php score.php [path/to/usage-log.json]
 */

const REFERENCE_ARM = 'altair';
const METRICS = ['total_tokens', 'turns', 'tool_calls', 'file_reads', 'wallclock_ms'];

/**
 * @param list<float|int> $numbers
 */
function median(array $numbers): float
{
    if ($numbers === []) {
        return 0.0;
    }

    sort($numbers);
    $count = \count($numbers);
    $mid = intdiv($count, 2);

    return $count % 2 === 1
        ? (float) $numbers[$mid]
        : (((float) $numbers[$mid - 1]) + ((float) $numbers[$mid])) / 2;
}

/**
 * @param array<string, mixed> $record
 */
function totalTokens(array $record): int
{
    return (int) ($record['input_tokens'] ?? 0) + (int) ($record['output_tokens'] ?? 0);
}

/**
 * @param  list<array<string, mixed>> $records
 * @return array<string, list<array<string, mixed>>>
 */
function groupByArm(array $records): array
{
    $grouped = [];
    foreach ($records as $record) {
        $arm = (string) ($record['arm'] ?? 'unknown');
        $grouped[$arm][] = $record;
    }
    ksort($grouped);

    return $grouped;
}

/**
 * @param  list<array<string, mixed>> $runs
 * @return array<string, mixed>
 */
function summarizeArm(string $arm, array $runs): array
{
    $series = array_fill_keys(METRICS, []);
    $passed = 0;

    foreach ($runs as $run) {
        $series['total_tokens'][] = totalTokens($run);
        $series['turns'][] = (int) ($run['turns'] ?? 0);
        $series['tool_calls'][] = (int) ($run['tool_calls'] ?? 0);
        $series['file_reads'][] = (int) ($run['file_reads'] ?? 0);
        $series['wallclock_ms'][] = (int) ($run['wallclock_ms'] ?? 0);
        $passed += ($run['acceptance_pass'] ?? false) === true ? 1 : 0;
    }

    $summary = ['arm' => $arm, 'runs' => \count($runs)];
    foreach (METRICS as $metric) {
        $summary[$metric] = [
            'median' => median($series[$metric]),
            'min' => $series[$metric] === [] ? 0 : min($series[$metric]),
            'max' => $series[$metric] === [] ? 0 : max($series[$metric]),
        ];
    }
    $summary['pass_at_1'] = $runs === [] ? 0.0 : round($passed / \count($runs), 3);

    return $summary;
}

/**
 * @param  array<string, array<string, mixed>> $summaries
 * @return array<string, array<string, float>>
 */
function comparisons(array $summaries): array
{
    if (!isset($summaries[REFERENCE_ARM])) {
        return [];
    }

    $reference = $summaries[REFERENCE_ARM];
    $comparison = [];
    foreach ($summaries as $arm => $summary) {
        if ($arm === REFERENCE_ARM) {
            continue;
        }
        foreach (METRICS as $metric) {
            $refMedian = (float) $reference[$metric]['median'];
            $comparison[$arm][$metric] = $refMedian > 0.0
                ? round(((float) $summary[$metric]['median']) / $refMedian, 2)
                : 0.0;
        }
    }

    return $comparison;
}

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

// --- Load ------------------------------------------------------------------

$path = $argv[1] ?? __DIR__ . '/results/usage-log.json';
if (!is_file($path)) {
    fail(\sprintf("Usage log '%s' not found. Try: php score.php results/usage-log.sample.json", $path));
}

$decoded = json_decode((string) file_get_contents($path), true);
if (!\is_array($decoded) || $decoded === []) {
    fail(\sprintf("Usage log '%s' is empty or not a JSON array of run records.", $path));
}

/** @var list<array<string, mixed>> $records */
$records = array_values(array_filter($decoded, '\is_array'));

// --- Aggregate -------------------------------------------------------------

$summaries = [];
foreach (groupByArm($records) as $arm => $runs) {
    $summaries[$arm] = summarizeArm($arm, $runs);
}

$results = [
    'source' => basename($path),
    'reference_arm' => REFERENCE_ARM,
    'arms' => array_values($summaries),
    'comparison' => comparisons($summaries),
    'note' => 'comparison = baseline median / altair median (higher means a larger Altair advantage)',
];

$outputDir = __DIR__ . '/results';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0o755, true);
}
file_put_contents(
    $outputDir . '/results.json',
    json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
);

// --- Report ----------------------------------------------------------------

printf("%-12s %6s %14s %8s %11s %13s %9s%s", 'arm', 'runs', 'tokens(med)', 'turns', 'toolcalls', 'wallclock(s)', 'pass@1', PHP_EOL);
printf("%s%s", str_repeat('-', 78), PHP_EOL);
foreach ($summaries as $summary) {
    printf(
        "%-12s %6d %14s %8s %11s %13s %9s%s",
        $summary['arm'],
        $summary['runs'],
        number_format($summary['total_tokens']['median']),
        number_format($summary['turns']['median']),
        number_format($summary['tool_calls']['median']),
        number_format($summary['wallclock_ms']['median'] / 1000, 1),
        number_format($summary['pass_at_1'] * 100, 0) . '%',
        PHP_EOL,
    );
}

$comparison = $results['comparison'];
if ($comparison !== []) {
    printf("%sComparison vs '%s' (x = how many times more the baseline spends):%s", PHP_EOL, REFERENCE_ARM, PHP_EOL);
    foreach ($comparison as $arm => $ratios) {
        printf(
            "  %s: %sx tokens, %sx turns, %sx wallclock%s",
            $arm,
            $ratios['total_tokens'],
            $ratios['turns'],
            $ratios['wallclock_ms'],
            PHP_EOL,
        );
    }
}

printf("%sWrote %s%s", PHP_EOL, $outputDir . '/results.json', PHP_EOL);

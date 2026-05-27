<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Introspection\Inspector;

use Altair\Http\Collection\MiddlewareCollection;
use Altair\Introspection\Result\InspectionTable;

/**
 * Reads the PSR-15 middleware pipeline as wired into a {@see
 * MiddlewareCollection} (a `Queue` of middleware refs).
 *
 * We walk the collection's items directly rather than instantiating the
 * Relay so this stays lazy-binding safe.
 *
 * Hosts that maintain multiple named pipelines should register a
 * separate inspector instance per pipeline; this class deliberately
 * takes a single collection to keep the API explicit.
 */
final readonly class PipelineInspector
{
    public function __construct(
        private MiddlewareCollection $queue,
        private string $pipelineName = 'default',
    ) {}

    public function inspectAll(): InspectionTable
    {
        $rows = [];
        $position = 0;
        foreach ($this->queue as $middleware) {
            $rows[] = [
                'position' => $position++,
                'middleware' => $this->describeMiddleware($middleware),
            ];
        }

        return new InspectionTable(
            title: \sprintf("Middleware pipeline '%s' (in dispatch order)", $this->pipelineName),
            columns: ['position', 'middleware'],
            rows: $rows,
            extras: ['pipeline' => $this->pipelineName, 'total' => \count($rows)],
        );
    }

    private function describeMiddleware(mixed $middleware): string
    {
        if (\is_string($middleware)) {
            return $middleware;
        }

        if (\is_object($middleware)) {
            return $middleware::class;
        }

        if (\is_array($middleware) && \count($middleware) === 2 && \is_string($middleware[1])) {
            $left = \is_object($middleware[0]) ? $middleware[0]::class : (string) $middleware[0];

            return $left . '::' . $middleware[1];
        }

        return '(unresolvable)';
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Introspection\Inspector;

use Altair\Http\Collection\RouteCollection;
use Altair\Introspection\Exception\NotFoundException;
use Altair\Introspection\Result\InspectionTable;

/**
 * Walks the framework's RouteCollection — which is a `Map<"METHOD PATH",
 * action>` per FastRouteConfiguration — and produces a sortable view.
 *
 * Walking the collection never triggers route dispatch or middleware
 * resolution, so this is safe to call from any context including a CLI
 * command booted with no HTTP request in scope.
 */
final readonly class RouteInspector
{
    public function __construct(
        private RouteCollection $routes,
    ) {}

    public function inspectAll(): InspectionTable
    {
        $rows = [];
        foreach ($this->routes as $request => $action) {
            [$method, $path] = $this->splitRequest($request);
            $rows[] = [
                'method' => $method,
                'path' => $path,
                'action' => $this->describeAction($action),
            ];
        }

        usort(
            $rows,
            static fn(array $a, array $b): int => [$a['path'], $a['method']] <=> [$b['path'], $b['method']],
        );

        return new InspectionTable(
            title: 'Registered routes',
            columns: ['method', 'path', 'action'],
            rows: $rows,
            extras: ['total' => \count($rows)],
        );
    }

    /**
     * Detail view for a single path (matches the first registration —
     * routes are keyed by `METHOD PATH`, so the same path can appear
     * under multiple methods).
     */
    public function inspectOne(string $path): InspectionTable
    {
        $matches = [];
        foreach ($this->routes as $request => $action) {
            [$method, $registeredPath] = $this->splitRequest($request);
            if ($registeredPath === $path) {
                $matches[] = [
                    'method' => $method,
                    'path' => $registeredPath,
                    'action' => $this->describeAction($action),
                ];
            }
        }

        if ($matches === []) {
            throw new NotFoundException(\sprintf("No route registered for path '%s'.", $path));
        }

        return new InspectionTable(
            title: \sprintf('Route detail: %s', $path),
            columns: ['method', 'path', 'action'],
            rows: $matches,
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitRequest(string $request): array
    {
        $parts = explode(' ', $request, 2);
        if (\count($parts) !== 2) {
            return ['?', $request];
        }

        return [$parts[0], $parts[1]];
    }

    private function describeAction(mixed $action): string
    {
        if (\is_string($action)) {
            return $action;
        }

        if (\is_array($action) && \count($action) === 2 && \is_string($action[1])) {
            $left = \is_object($action[0]) ? $action[0]::class : (string) $action[0];

            return $left . '::' . $action[1];
        }

        if (\is_object($action)) {
            return $action::class;
        }

        return '(unresolvable)';
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Suggest\Rule;

use Altair\Suggest\Contracts\SuggestionRuleInterface;
use Altair\Suggest\Result\Severity;
use Altair\Suggest\Result\Suggestion;
use Altair\Suggest\Snapshot\RouteNode;
use Altair\Suggest\Snapshot\Snapshot;
use Override;

/**
 * A route registered at runtime that no scaffolder spec covers. Hand-written
 * Action/Input/Responder triples drift from the spec workflow — surfacing
 * them nudges the route back under `spec:scaffold`, where the OpenAPI
 * fragment, the test, and the SDK stay in sync.
 *
 * Silent unless the project actually uses specs: with no specs collected
 * there is no spec workflow to drift from, so every route would be a false
 * positive.
 */
final readonly class RouteWithoutSpecRule implements SuggestionRuleInterface
{
    #[Override]
    public function name(): string
    {
        return 'route_without_spec';
    }

    #[Override]
    public function analyse(Snapshot $snapshot): array
    {
        if ($snapshot->specs === [] || $snapshot->routes === []) {
            return [];
        }

        $out = [];
        foreach ($snapshot->routes as $route) {
            if (!$this->covered($route, $snapshot)) {
                $subject = \sprintf('%s %s', $route->method, $route->path);
                $out[] = new Suggestion(
                    rule: $this->name(),
                    severity: Severity::Info,
                    subject: $subject,
                    message: \sprintf('Route %s has no scaffolder spec — it appears to be hand-wired.', $subject),
                    fix: 'Capture it as a YAML spec and run `bin/altair spec:scaffold`.',
                );
            }
        }

        return $out;
    }

    private function covered(RouteNode $route, Snapshot $snapshot): bool
    {
        foreach ($snapshot->specs as $spec) {
            if (strcasecmp($spec->method, $route->method) === 0 && $this->samePath($spec->route, $route->path)) {
                return true;
            }
        }

        return false;
    }

    private function samePath(string $a, string $b): bool
    {
        return $this->normalise($a) === $this->normalise($b);
    }

    private function normalise(string $path): string
    {
        $trimmed = rtrim($path, '/');

        return $trimmed === '' ? '/' : $trimmed;
    }
}

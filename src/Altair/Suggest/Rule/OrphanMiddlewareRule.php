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
use Altair\Suggest\Snapshot\Snapshot;
use Override;
use Psr\Http\Server\MiddlewareInterface;

/**
 * A PSR-15 middleware bound in the Container but absent from the default
 * pipeline. It either belongs in the pipeline (a wiring bug) or it is dead.
 *
 * Hedged to `info` because hosts may run multiple named pipelines and the
 * snapshot only captures the default one; silent when no pipeline was
 * inspected, so a worker-only host produces no false orphans.
 */
final readonly class OrphanMiddlewareRule implements SuggestionRuleInterface
{
    #[Override]
    public function name(): string
    {
        return 'orphan_middleware';
    }

    #[Override]
    public function analyse(Snapshot $snapshot): array
    {
        if ($snapshot->middleware === []) {
            return [];
        }

        $out = [];
        foreach ($snapshot->bindings as $binding) {
            if (!$binding->implements(MiddlewareInterface::class)) {
                continue;
            }

            if ($this->inPipeline($binding->id, $binding->target, $snapshot->middleware)) {
                continue;
            }

            $out[] = new Suggestion(
                rule: $this->name(),
                severity: Severity::Info,
                subject: $binding->id,
                message: \sprintf('Middleware %s is bound in the container but not in the default pipeline.', $binding->id),
                fix: 'Add it to the pipeline, or remove the binding if it is unused.',
            );
        }

        return $out;
    }

    /**
     * @param list<string> $pipeline
     */
    private function inPipeline(string $id, string $target, array $pipeline): bool
    {
        foreach ($pipeline as $entry) {
            if (strcasecmp($entry, $id) === 0 || strcasecmp($entry, $target) === 0) {
                return true;
            }
        }

        return false;
    }
}

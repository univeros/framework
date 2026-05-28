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

/**
 * A binding whose constructor pulls in more collaborators than the threshold
 * is doing too much — a split-this-class smell. Counts object-typed
 * constructor parameters only (scalars are configuration, not collaborators).
 */
final readonly class FatConstructorRule implements SuggestionRuleInterface
{
    public const int DEFAULT_THRESHOLD = 5;

    public function __construct(
        private int $threshold = self::DEFAULT_THRESHOLD,
    ) {}

    #[Override]
    public function name(): string
    {
        return 'fat_constructor';
    }

    #[Override]
    public function analyse(Snapshot $snapshot): array
    {
        $out = [];
        foreach ($snapshot->bindings as $binding) {
            $count = \count($binding->dependencies);
            if ($count > $this->threshold) {
                $out[] = new Suggestion(
                    rule: $this->name(),
                    severity: Severity::Info,
                    subject: $binding->id,
                    message: \sprintf(
                        '%s has %d constructor dependencies (threshold %d) — consider splitting its responsibilities.',
                        $binding->id,
                        $count,
                        $this->threshold,
                    ),
                    fix: 'Extract collaborators into a smaller, focused service or a facade.',
                );
            }
        }

        return $out;
    }
}

<?php

declare(strict_types=1);

namespace Altair\Tests\Suggest\Support;

use Altair\Suggest\Contracts\SuggestionRuleInterface;
use Altair\Suggest\Result\Suggestion;
use Altair\Suggest\Snapshot\Snapshot;
use Override;

/**
 * A rule that yields a pre-baked list of suggestions, recording whether it
 * was asked to analyse — used to exercise the engine's filtering and the
 * registry without standing up real heuristics.
 */
final class FakeRule implements SuggestionRuleInterface
{
    public bool $analysed = false;

    /**
     * @param list<Suggestion> $suggestions
     */
    public function __construct(
        private readonly string $name,
        private readonly array $suggestions = [],
    ) {}

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function analyse(Snapshot $snapshot): array
    {
        $this->analysed = true;

        return $this->suggestions;
    }
}

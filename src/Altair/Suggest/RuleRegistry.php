<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Suggest;

use Altair\Suggest\Contracts\SuggestionRuleInterface;

/**
 * The ordered set of rules a suggest run evaluates.
 *
 * Order is the report's grouping order for rules of equal severity. Hosts
 * extend the set by `add()`-ing their own rules (typically via a Container
 * `prepare` hook after {@see Configuration\SuggestConfiguration} runs).
 */
final class RuleRegistry
{
    /**
     * @var list<SuggestionRuleInterface>
     */
    private array $rules = [];

    /**
     * @param list<SuggestionRuleInterface> $rules
     */
    public function __construct(array $rules = [])
    {
        foreach ($rules as $rule) {
            $this->add($rule);
        }
    }

    public function add(SuggestionRuleInterface $rule): void
    {
        $this->rules[] = $rule;
    }

    /**
     * @return list<SuggestionRuleInterface>
     */
    public function all(): array
    {
        return $this->rules;
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Suggest\Result;

/**
 * One actionable finding from one rule.
 *
 * `subject` is the thing the suggestion is about (a binding id, a route, an
 * event name) so an agent can group or de-duplicate by it; `fix` is the
 * optional human hint for the next action. The shape is deliberately flat
 * and string-typed so the JSON projection is trivially stable.
 */
final readonly class Suggestion
{
    public function __construct(
        public string $rule,
        public Severity $severity,
        public string $subject,
        public string $message,
        public ?string $fix = null,
    ) {}

    /**
     * Deterministic projection: fixed key order, `fix` omitted when absent.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $out = [
            'rule' => $this->rule,
            'severity' => $this->severity->value,
            'subject' => $this->subject,
            'message' => $this->message,
        ];

        if ($this->fix !== null) {
            $out['fix'] = $this->fix;
        }

        return $out;
    }
}

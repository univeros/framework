<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Doctor\Result;

/**
 * The outcome of running one check.
 *
 * Built via the named constructors so the optional fields (`fix`,
 * `agentAction`, `source`) only ever appear where they make sense — on a
 * non-ok result that has a remediation.
 */
final readonly class CheckResult
{
    private function __construct(
        public string $name,
        public CheckStatus $status,
        public string $detail,
        public ?string $fix = null,
        public ?AgentAction $agentAction = null,
        public ?string $source = null,
    ) {}

    public static function ok(string $name, string $detail): self
    {
        return new self($name, CheckStatus::Ok, $detail);
    }

    public static function skipped(string $name, string $detail): self
    {
        return new self($name, CheckStatus::Skipped, $detail);
    }

    public static function warn(
        string $name,
        string $detail,
        ?string $fix = null,
        ?AgentAction $agentAction = null,
        ?string $source = null,
    ): self {
        return new self($name, CheckStatus::Warn, $detail, $fix, $agentAction, $source);
    }

    public static function error(
        string $name,
        string $detail,
        ?string $fix = null,
        ?AgentAction $agentAction = null,
        ?string $source = null,
    ): self {
        return new self($name, CheckStatus::Error, $detail, $fix, $agentAction, $source);
    }

    /**
     * Deterministic projection: fixed key order, optional fields omitted
     * when absent (no nulls in the JSON, no timestamps).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'name' => $this->name,
            'status' => $this->status->value,
            'detail' => $this->detail,
        ];

        if ($this->fix !== null) {
            $out['fix'] = $this->fix;
        }

        if ($this->agentAction instanceof AgentAction) {
            $out['agent_action'] = $this->agentAction->toArray();
        }

        if ($this->source !== null) {
            $out['source'] = $this->source;
        }

        return $out;
    }
}

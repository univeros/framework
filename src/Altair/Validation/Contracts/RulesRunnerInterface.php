<?php
namespace Altair\Validation\Contracts;

use Altair\Middleware\Contracts\MiddlewareRunnerInterface;

interface RulesRunnerInterface extends MiddlewareRunnerInterface
{
    /**
     * Resets internal queue with new rules returns itself.
     *
     * @param array $rules
     *
     * @return RulesRunnerInterface
     */
    public function withRules(array $rules): RulesRunnerInterface;
}

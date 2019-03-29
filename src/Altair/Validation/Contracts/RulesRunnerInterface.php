<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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

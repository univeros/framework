<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation\Contracts;

use Altair\Middleware\Contracts\MiddlewareRunnerInterface;

interface FiltersRunnerInterface extends MiddlewareRunnerInterface
{
    /**
     * Resets the internal queue with new array of filters and returns itself.
     *
     * @param array $filters
     *
     * @return FiltersRunnerInterface
     */
    public function withFilters(array $filters): FiltersRunnerInterface;
}

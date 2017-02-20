<?php
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

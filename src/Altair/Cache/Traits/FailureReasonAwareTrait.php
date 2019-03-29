<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cache\Traits;

trait FailureReasonAwareTrait
{
    protected $reason;

    /**
     * Returns the error message if validation has failed, if it has failed.
     *
     * @return string|null
     */
    public function getFailureReason(): ?string
    {
        return $this->reason;
    }
}

<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation\Contracts;

use Altair\Middleware\Contracts\PayloadInterface;

interface SanitizerInterface
{
    /**
     * Validates an input against the collection of rules the validator has.
     *
     * @param SanitizableInterface $sanitizable
     *
     * @return SanitizableInterface
     */
    public function sanitize(SanitizableInterface $sanitizable): SanitizableInterface;

    /**
     * Returns the payload that went throughout the queue of validation rules.
     *
     * @return PayloadInterface|null
     */
    public function getPayload(): ?PayloadInterface;
}

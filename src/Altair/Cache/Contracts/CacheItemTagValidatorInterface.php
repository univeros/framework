<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cache\Contracts;

interface CacheItemTagValidatorInterface extends FailureReasonAwareInterface
{
    /**
     * Checks whether a cache tag is valid and if not. If valid will return true, false otherwise.
     *
     * @param string $tag
     *
     * @return bool
     */
    public function validate(string $tag): bool;
}

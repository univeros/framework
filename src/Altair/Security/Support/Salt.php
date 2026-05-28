<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Security\Support;

use Altair\Security\Exception\InvalidArgumentException;
use Exception;

class Salt
{
    /**
     * @throws Exception
     */
    public function generate(int $length = 32): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException(
                \sprintf('Salt length must be a positive integer, "%d" given.', $length)
            );
        }

        return substr(strtr(base64_encode(random_bytes($length)), '+/=', '_-.'), 0, $length);
    }
}

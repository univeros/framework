<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Security\Support;

use Exception;

class Salt
{
    /**
     * @param int $length
     * @throws Exception
     * @return string
     */
    public function generate(int $length = 32): string
    {
        return substr(strtr(base64_encode(random_bytes($length)), '+/=', '_-.'), 0, $length);
    }
}

<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cookie;

use Altair\Cookie\Contracts\CookieInterface;
use Altair\Cookie\Traits\NameAndValueAwareTrait;

class Cookie extends AbstractCookie implements CookieInterface
{
    /**
     * Cookie constructor.
     * @param string $name
     * @param string|null $value
     */
    public function __construct(string $name, string $value = null)
    {
        parent::__construct($name, $value);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return urlencode($this->name) . '=' . urlencode($this->value);
    }

    /**
     * @param string|null $value
     *
     * @return Cookie
     */
    public function withValue(string $value = null): Cookie
    {
        $cloned = clone $this;
        $cloned->value = $value;

        return $cloned;
    }
}

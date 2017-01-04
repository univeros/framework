<?php
namespace Altair\Cookie;

use Altair\Cookie\Contracts\CookieInterface;
use Altair\Cookie\Traits\NameAndValueAwareTrait;

class Cookie implements CookieInterface
{
    use NameAndValueAwareTrait;

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

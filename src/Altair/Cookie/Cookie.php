<?php
namespace Altair\Cookie;

use Altair\Cookie\Contracts\CookieInterface;
use Altair\Cookie\Traits\NameAndValueAwareTrait;

class Cookie implements CookieInterface
{
    use NameAndValueAwareTrait;

    public function __toString()
    {
        return urlencode($this->name) . '=' . urlencode($this->value);
    }

    public function withValue(string $value = null): Cookie
    {
        $cloned = clone $this;
        $cloned->value = $value;

        return $cloned;
    }
}

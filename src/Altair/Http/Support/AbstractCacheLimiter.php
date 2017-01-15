<?php
namespace Altair\Http\Support;

use Altair\Http\Contracts\CacheLimiterInterface;

abstract class AbstractCacheLimiter implements CacheLimiterInterface
{
    /**
     *
     * The cache expiration time in minutes.
     *
     * @var int
     *
     * @see session_cache_expire()
     *
     */
    protected $cacheExpire;
    /**
     *
     * The current Unix timestamp.
     *
     * @var int
     *
     */
    protected $time;

    /**
     * AbstractCacheLimiter constructor.
     *
     * @param int $cacheExpire
     */
    public function __construct(int $cacheExpire = 180)
    {
        $this->time = time();
        $this->cacheExpire = $cacheExpire;
    }

    /**
     * Returns a cookie-formatted timestamp.
     *
     * @param int $adjust the time by this many seconds before formatting.
     *
     * @return string
     */
    protected function timestamp(int $adjust = 0): string
    {
        return gmdate('D, d M Y H:i:s T', $this->time + $adjust);
    }
}

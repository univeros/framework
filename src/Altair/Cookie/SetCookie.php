<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cookie;

use Altair\Cookie\Contracts\SetCookieInterface;
use DateTime;
use DateTimeInterface;
use Exception;

class SetCookie extends AbstractCookie implements SetCookieInterface, \Stringable
{
    /**
     * @var int
     */
    protected $expires = 0;

    /**
     * @var int
     */
    protected $maxAge = 0;

    /**
     * @var
     */
    protected $path;

    /**
     * @var
     */
    protected $domain;

    /**
     * @var bool
     */
    protected $secure = false;

    /**
     * @var bool
     */
    protected $httpOnly = false;

    /**
     * Cookie constructor.
     * @param string|null $value
     */
    public function __construct(string $name, string $value = null)
    {
        parent::__construct($name, $value);
    }

    #[\Override]
    public function __toString(): string
    {
        $parts = [
            urlencode($this->name) . '=' . ($this->value ? urlencode($this->value) : ''),
        ];

        $parts[] = $this->domain ? sprintf('Domain=%s', $this->domain) : null;
        $parts[] = $this->path ? sprintf('Path=%s', $this->path) : null;
        $parts[] = $this->expires ? sprintf('Expires=%s', gmdate('D, d M Y H:i:s T', $this->expires)) : null;
        $parts[] = $this->maxAge ? sprintf('Max-Age=%s', $this->maxAge) : null;
        $parts[] = $this->secure ? 'Secure' : null;
        $parts[] = $this->httpOnly ? 'HttpOnly' : null;

        return implode('; ', array_filter($parts));
    }

    #[\Override]
    public function getExpires(): int
    {
        return $this->expires;
    }

    #[\Override]
    public function getMaxAge(): int
    {
        return $this->maxAge;
    }

    #[\Override]
    public function getPath(): ?string
    {
        return $this->path;
    }

    #[\Override]
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    #[\Override]
    public function getSecure(): bool
    {
        return $this->secure;
    }

    #[\Override]
    public function getHttpOnly(): bool
    {
        return $this->httpOnly;
    }


    public function withValue($value = null): SetCookie
    {
        $clone = clone $this;
        $clone->value = $value;

        return $clone;
    }

    /**
     * @param int|DateTime|DateTimeInterface|string|null $expires
     */
    public function withExpires($expires = null): SetCookie
    {
        $expires = $this->resolveExpires($expires);

        $clone = clone $this;
        $clone->expires = $expires;

        return $clone;
    }


    public function withMaxAge(int $maxAge = null): SetCookie
    {
        $clone = clone $this;
        $clone->maxAge = $maxAge;

        return $clone;
    }


    public function withPath(string $path = null): SetCookie
    {
        $clone = clone $this;
        $clone->path = $path;

        return $clone;
    }


    public function withDomain($domain = null): SetCookie
    {
        $clone = clone $this;
        $clone->domain = $domain;

        return $clone;
    }


    public function withSecure(bool $secure = null): SetCookie
    {
        $clone = clone $this;
        $clone->secure = $secure;

        return $clone;
    }


    public function withHttpOnly(bool $httpOnly): SetCookie
    {
        $clone = clone $this;
        $clone->httpOnly = $httpOnly;

        return $clone;
    }

    /**
     *@throws Exception
     */
    public function remember(): SetCookie
    {
        return $this->withExpires(new DateTime('+5 years'));
    }

    /**
     *@throws Exception
     */
    public function expire(): SetCookie
    {
        return $this->withExpires(new DateTime('-5 years'));
    }

    /**
     * @param int|DateTime|DateTimeInterface|string|null $expires
     */
    protected function resolveExpires($expires = null): ?int
    {
        if ($expires === null) {
            return $expires;
        }

        if ($expires instanceof DateTimeInterface) {
            return $expires->getTimestamp();
        }

        if (is_numeric($expires)) {
            return $expires;
        }

        if (is_string($expires)) {
            $expiresTime = strtotime($expires);

            return false !== $expiresTime ? $expiresTime : null;
        }
    }
}

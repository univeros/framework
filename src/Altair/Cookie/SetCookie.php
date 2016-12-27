<?php
namespace Altair\Cookie;

use Altair\Cookie\Contracts\SetCookieInterface;
use Altair\Cookie\Traits\NameAndValueAwareTrait;
use DateTime;
use DateTimeInterface;

class SetCookie implements SetCookieInterface
{
    use NameAndValueAwareTrait;

    protected $expires = 0;
    protected $maxAge = 0;
    protected $path;
    protected $domain;
    protected $secure = false;
    protected $httpOnly = false;

    public function __toString()
    {
        $parts = [
            urlencode($this->name) . '=' . urlencode($this->value)
        ];

        $parts[] = $this->domain ? sprintf('Domain=%s', $this->domain) : null;
        $parts[] = $this->path ? sprintf('Path=%s', $this->path) : null;
        $parts[] = $this->expires ? sprintf("Expires=%s", gmdate('D, d M Y H:i:s T', $this->expires)) : null;
        $parts[] = $this->maxAge ? sprintf("Max-Age=%s", $this->maxAge) : null;
        $parts[] = $this->secure ? 'Secure' : null;
        $parts[] = $this->httpOnly ? 'HttpOnly' : null;

        return implode('; ', array_filter($parts));
    }

    public function getExpires(): int
    {
        return $this->expires;
    }

    public function getMaxAge(): int
    {
        return $this->maxAge;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function getSecure(): bool
    {
        return $this->secure;
    }

    public function getHttpOnly(): bool
    {
        return $this->httpOnly;
    }

    public function withExpires($expires): SetCookie
    {
        $expires = $this->resolveExpires($expires);

        $clone = clone $this;
        $clone->expires = $expires;

        return $clone;
    }

    public function withMaxAge(int $maxAge): SetCookie
    {
        $clone = clone($this);
        $clone->maxAge = $maxAge;

        return $clone;
    }

    public function withPath(string $path): SetCookie
    {
        $clone = clone($this);
        $clone->path = $path;

        return $clone;
    }

    public function withDomain($domain = null): SetCookie
    {
        $clone = clone($this);
        $clone->domain = $domain;

        return $clone;
    }

    public function withSecure(bool $secure): SetCookie
    {
        $clone = clone($this);
        $clone->secure = $secure;

        return $clone;
    }

    public function withHttpOnly(bool $httpOnly): SetCookie
    {
        $clone = clone($this);
        $clone->httpOnly = $httpOnly;

        return $clone;
    }

    public function remember(): SetCookie
    {
        return $this->withExpires(new DateTime('+5 years'));
    }

    public function expire(): SetCookie
    {
        return $this->withExpires(new DateTime('-5 years'));
    }

    /**
     * @param int|DateTime|DateTimeInterface|string|null $expires
     *
     * @return int|null
     */
    protected function resolveExpires($expires = null): ?int
    {
        if ($expires === null) {
            return $expires;
        }
        if ($expires instanceof DateTime || $expires instanceof DateTimeInterface) {
            return $expires->getTimestamp();
        }
        if (is_numeric($expires)) {
            return $expires;
        }
        if (is_string($expires)) {
            $expires = strtotime($expires);

            return $expires !== false ? $expires : null;
        }
    }
}

<?php

declare(strict_types=1);

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
use Stringable;

final readonly class SetCookie extends AbstractCookie implements SetCookieInterface, Stringable
{
    public function __construct(
        string $name,
        ?string $value = null,
        protected int $expires = 0,
        protected int $maxAge = 0,
        protected ?string $path = null,
        protected ?string $domain = null,
        protected bool $secure = false,
        protected bool $httpOnly = false,
    ) {
        parent::__construct($name, $value);
    }

    #[\Override]
    public function __toString(): string
    {
        $parts = [
            urlencode($this->name) . '=' . ($this->value !== null && $this->value !== '' ? urlencode($this->value) : ''),
        ];

        $parts[] = $this->domain !== null && $this->domain !== '' ? sprintf('Domain=%s', $this->domain) : null;
        $parts[] = $this->path !== null && $this->path !== '' ? sprintf('Path=%s', $this->path) : null;
        $parts[] = $this->expires !== 0 ? sprintf('Expires=%s', gmdate('D, d M Y H:i:s T', $this->expires)) : null;
        $parts[] = $this->maxAge !== 0 ? sprintf('Max-Age=%s', $this->maxAge) : null;
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

    public function withValue(?string $value = null): self
    {
        return $this->with(value: $value);
    }

    /**
     * @param int|DateTime|DateTimeInterface|string|null $expires
     */
    public function withExpires($expires = null): self
    {
        return $this->with(expires: $this->resolveExpires($expires) ?? 0);
    }

    public function withMaxAge(?int $maxAge = null): self
    {
        return $this->with(maxAge: $maxAge ?? 0);
    }

    public function withPath(?string $path = null): self
    {
        return $this->with(path: $path);
    }

    public function withDomain(?string $domain = null): self
    {
        return $this->with(domain: $domain);
    }

    public function withSecure(?bool $secure = null): self
    {
        return $this->with(secure: (bool) $secure);
    }

    public function withHttpOnly(bool $httpOnly): self
    {
        return $this->with(httpOnly: $httpOnly);
    }

    /**
     * @throws Exception
     */
    public function remember(): self
    {
        return $this->withExpires(new DateTime('+5 years'));
    }

    /**
     * @throws Exception
     */
    public function expire(): self
    {
        return $this->withExpires(new DateTime('-5 years'));
    }

    /**
     * Internal copy-with-overrides. Each parameter, if non-null, replaces the
     * corresponding property; nulls mean "keep current value". To explicitly
     * set a property to null, the caller's public with* method should map that
     * to a defined sentinel (typically 0 or '').
     */
    private function with(
        ?string $value = null,
        ?int $expires = null,
        ?int $maxAge = null,
        ?string $path = null,
        ?string $domain = null,
        ?bool $secure = null,
        ?bool $httpOnly = null,
    ): self {
        return new self(
            name: $this->name,
            value: $value ?? $this->value,
            expires: $expires ?? $this->expires,
            maxAge: $maxAge ?? $this->maxAge,
            path: $path ?? $this->path,
            domain: $domain ?? $this->domain,
            secure: $secure ?? $this->secure,
            httpOnly: $httpOnly ?? $this->httpOnly,
        );
    }

    /**
     * @param int|DateTime|DateTimeInterface|string|null $expires
     */
    private function resolveExpires($expires = null): ?int
    {
        if ($expires === null) {
            return null;
        }

        if ($expires instanceof DateTimeInterface) {
            return $expires->getTimestamp();
        }

        if (is_numeric($expires)) {
            return (int) $expires;
        }

        if (is_string($expires)) {
            $expiresTime = strtotime($expires);

            return $expiresTime !== false ? $expiresTime : null;
        }

        return null;
    }
}

<?php
namespace Altair\Cookie\Contracts;

interface SetCookieInterface extends CookieInterface
{
    const HEADER = 'Set-Cookie';

    /**
     * @return int
     */
    public function getExpires(): int;

    /**
     * @return int
     */
    public function getMaxAge(): int;

    /**
     * @return null|string
     */
    public function getPath(): ?string;

    /**
     * @return null|string
     */
    public function getDomain(): ?string;

    /**
     * @return bool
     */
    public function getSecure(): bool;

    /**
     * @return bool
     */
    public function getHttpOnly(): bool;
}

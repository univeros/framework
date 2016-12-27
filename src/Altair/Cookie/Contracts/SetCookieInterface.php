<?php
namespace Altair\Cookie\Contracts;

interface SetCookieInterface extends CookieInterface
{
    const HEADER = 'Set-Cookie';

    public function getExpires(): int;

    public function getMaxAge(): int;

    public function getPath(): ?string;

    public function getDomain(): ?string;

    public function getSecure(): bool;

    public function getHttpOnly(): bool;
}

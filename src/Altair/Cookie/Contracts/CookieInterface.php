<?php
namespace Altair\Cookie\Contracts;

interface CookieInterface
{
    /**
     * The name of the cookie header
     */
    const HEADER = 'Cookie';

    /**
     * Returns the name of the cookie
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Returns the value of the cookie
     *
     * @return null|string
     */
    public function getValue(): ?string;
}

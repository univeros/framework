<?php
namespace Altair\Session\Contracts;

interface CsrfTokenInterface
{
    /**
     * Checks whether an incoming CSRF token value is valid.
     *
     * @param string $value
     *
     * @return bool
     */
    public function isValid(string $value): bool;

    /**
     * Returns the value of the outgoing CSRF token.
     *
     * @return string
     */
    public function getValue(): string;

    /**
     * Regenerates the value of the outgoing CSRF token.
     *
     * @return null
     */
    public function generateValue();
}

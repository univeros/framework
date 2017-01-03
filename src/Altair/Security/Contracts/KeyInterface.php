<?php
namespace Altair\Security\Contracts;

interface KeyInterface
{
    /**
     * Derives a key
     *
     * @return string
     */
    public function derive(): string;
}

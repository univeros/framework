<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Contracts;

use Altair\Http\Exception\AuthorizationTokenException;
use Altair\Http\Exception\InvalidTokenException;

interface TokenFactoryInterface
{
    /**
     * Builds a Token instance from a token string. The builder would validate the token and:
     *
     * - If the specified token is invalid, an InvalidTokenException instance will be thrown.
     * - If a valid token string is present, a corresponding Token instance will be returned.
     * - If the token validation fails for some reason, an AuthorizationTokenException will be thrown.
     *
     *
     * @throws InvalidTokenException
     * @throws AuthorizationTokenException
     *
     */
    public function fromTokenString(string $token): TokenInterface;

    /**
     * Builds a Token instance from credentials. The credentials are assumed to be valid. An IdentityValidatorInterface
     * object should grant that the credentials given are correct.
     *
     *
     * @see TokenAuthenticationMiddleware::__invoke()
     *
     * @param array<string, mixed> $credentials
     */
    public function fromCredentials(array $credentials): TokenInterface;
}

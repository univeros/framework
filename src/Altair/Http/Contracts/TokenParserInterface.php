<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Contracts;

use Altair\Http\Exception\InvalidTokenException;

interface TokenParserInterface
{
    /**
     * Parses a token string and returns a Token instance.
     *
     *
     * @throws InvalidTokenException
     *
     */
    public function parse(string $token): TokenInterface;
}

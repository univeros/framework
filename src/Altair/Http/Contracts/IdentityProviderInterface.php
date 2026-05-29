<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Contracts;

/**
 * Read-only lookup of an identity record for HTTP authentication.
 *
 * The host application implements this against whatever storage it uses
 * (a persistence repository, a flat config, a directory service). Keeping
 * the contract here lets the Http package authenticate without depending on
 * an ORM or on the Data package.
 */
interface IdentityProviderInterface
{
    /**
     * Locate a single identity record matching the given criteria.
     *
     * @param array<string, mixed> $criteria
     *
     * @return array<string, mixed>|null the record's fields, or null when no match exists
     */
    public function findOneBy(array $criteria): ?array;
}

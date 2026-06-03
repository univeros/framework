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
 * An exception that contributes extra members to an RFC 7807 problem document.
 *
 * The {@see \Altair\Http\Support\ProblemDetailsErrorHandler} merges the returned
 * map onto the problem JSON (without letting it clobber the reserved members
 * `type`, `title`, `status`, `instance`). A validation error, for example,
 * returns `['errors' => ['field' => 'message']]`.
 */
interface ProblemExtensionInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getProblemExtensions(): array;
}

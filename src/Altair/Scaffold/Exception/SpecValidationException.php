<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Exception;

class SpecValidationException extends ScaffoldException
{
    /**
     * @param list<string> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(\sprintf("Spec validation failed:\n - %s", implode("\n - ", $errors)));
    }
}

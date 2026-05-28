<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Exception;

/**
 * Thrown when request data cannot satisfy a typed input DTO — a required field
 * is missing. The HTTP action layer maps it to a 422 response carrying the
 * per-field errors.
 */
final class InputValidationException extends HttpException
{
    /**
     * @param array<string, string> $errors per-field error messages
     */
    public function __construct(
        public readonly array $errors,
        string $message = 'Input validation failed.',
    ) {
        parent::__construct($message);
    }
}

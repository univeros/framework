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
 * An exception that knows the HTTP status (and any headers) it should map to.
 *
 * The {@see \Altair\Http\Middleware\ExceptionHandlerMiddleware} reads these
 * off a thrown exception so a 404 renders as 404 instead of collapsing to a
 * generic 500. Userland exceptions can implement this to opt a domain error
 * into a specific status without subclassing the framework's exceptions.
 */
interface HttpExceptionInterface
{
    /**
     * The HTTP status code this exception maps to (a value in the 4xx/5xx range).
     */
    public function getStatusCode(): int;

    /**
     * Headers to merge onto the error response (e.g. `Allow` for a 405).
     *
     * @return array<string, string>
     */
    public function getHeaders(): array;
}

<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Contracts;

interface OutputFormatterInterface
{
    /**
     * Get the content types that this formatter can satisfy.
     *
     * @return array
     */
    public static function accepts(): array;

    /**
     * Get the content type of the response body.
     *
     * @return string
     */
    public function type(): string;

    /**
     * Get the response body from the payload.
     *
     * @param PayloadInterface $payload
     *
     * @return string
     */
    public function body(PayloadInterface $payload): string;
}

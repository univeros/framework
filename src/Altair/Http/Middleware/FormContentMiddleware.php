<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Middleware;

use Override;

class FormContentMiddleware extends AbstractContentHandlerMiddleware
{
    #[Override]
    protected function contentTypes(): array
    {
        return ['application/x-www-form-urlencoded'];
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function parse(string $body): array
    {
        parse_str($body, $parsed);

        $normalized = [];
        foreach ($parsed as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}

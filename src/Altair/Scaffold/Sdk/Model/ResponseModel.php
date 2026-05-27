<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Sdk\Model;

/**
 * One response of an {@see OperationModel}, keyed by HTTP status (or the
 * literal `default`). `schema` is null for empty-body responses.
 */
final readonly class ResponseModel
{
    public function __construct(
        public string $status,
        public ?SchemaType $schema,
        public string $description = '',
    ) {}

    public function isSuccess(): bool
    {
        if (!ctype_digit($this->status)) {
            return false;
        }

        $code = (int) $this->status;

        return $code >= 200 && $code < 300;
    }

    public function statusIsNumeric(): bool
    {
        return ctype_digit($this->status);
    }
}

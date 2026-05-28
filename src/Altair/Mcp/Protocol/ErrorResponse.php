<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Protocol;

/**
 * A JSON-RPC 2.0 error response.
 */
final readonly class ErrorResponse
{
    public function __construct(
        public int|string|null $id,
        public int $code,
        public string $message,
        public mixed $data = null,
    ) {}

    public static function from(int|string|null $id, ErrorCode $code, string $message, mixed $data = null): self
    {
        return new self($id, $code->value, $message, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $error = ['code' => $this->code, 'message' => $this->message];
        if ($this->data !== null) {
            $error['data'] = $this->data;
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $this->id,
            'error' => $error,
        ];
    }
}

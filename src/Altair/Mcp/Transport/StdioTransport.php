<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Transport;

use Altair\Mcp\Contracts\TransportInterface;
use Override;

use const STDIN;
use const STDOUT;

/**
 * Newline-delimited JSON over stdin/stdout — the transport Claude Desktop and
 * Cursor speak. One JSON-RPC message per line; blank lines are skipped.
 */
final class StdioTransport implements TransportInterface
{
    /**
     * @var resource
     */
    private $in;

    /**
     * @var resource
     */
    private $out;

    /**
     * @param resource|null $in
     * @param resource|null $out
     */
    public function __construct($in = null, $out = null)
    {
        $this->in = $in ?? STDIN;
        $this->out = $out ?? STDOUT;
    }

    #[Override]
    public function receive(): ?string
    {
        while (($line = fgets($this->in)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line !== '') {
                return $line;
            }
        }

        return null;
    }

    #[Override]
    public function send(string $message): void
    {
        fwrite($this->out, $message . "\n");
    }

    #[Override]
    public function close(): void {}
}

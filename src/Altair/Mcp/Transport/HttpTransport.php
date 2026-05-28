<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Transport;

use Altair\Mcp\Server\Server;

use const JSON_UNESCAPED_SLASHES;
use const STDERR;

use function stream_socket_accept;
use function stream_socket_server;

/**
 * Minimal HTTP front end for out-of-process agents: a single JSON-RPC message
 * per POST body, the reply returned as `application/json`. Notifications get a
 * `202 Accepted` with an empty body.
 *
 * This is deliberately request/response (no SSE) — sufficient for tools, which
 * never stream. {@see respond()} is pure and unit-testable; {@see serve()} wraps
 * it in a blocking accept loop for real use via `mcp serve --transport=http`.
 */
final readonly class HttpTransport
{
    private const int MAX_BODY_BYTES = 4 * 1024 * 1024;

    private const int READ_TIMEOUT_SECONDS = 30;

    public function __construct(private Server $server) {}

    /**
     * Translate a raw HTTP/1.1 request into a raw HTTP/1.1 response.
     */
    public function respond(string $rawRequest): string
    {
        [$head, $body] = $this->split($rawRequest);
        $method = strtoupper((string) strtok($head, " \t\r\n"));

        if ($method !== 'POST') {
            return $this->httpResponse(405, 'Method Not Allowed', json_encode([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32600, 'message' => 'Only POST is supported.'],
            ], JSON_UNESCAPED_SLASHES) ?: '{}');
        }

        $reply = $this->server->handle($body);
        if ($reply === null) {
            return $this->httpResponse(202, 'Accepted', '');
        }

        return $this->httpResponse(200, 'OK', $reply);
    }

    /**
     * Blocking accept loop. Returns only when the listener cannot be created.
     */
    public function serve(string $host = '127.0.0.1', int $port = 3737): int
    {
        $socket = @\stream_socket_server(\sprintf('tcp://%s:%d', $host, $port), $errno, $errstr);
        if ($socket === false) {
            fwrite(STDERR, \sprintf("Cannot bind %s:%d — %s (%d)\n", $host, $port, $errstr, $errno));

            return 1;
        }

        fwrite(STDERR, \sprintf("MCP HTTP transport listening on http://%s:%d\n", $host, $port));

        while (\is_resource($socket)) {
            $connection = @\stream_socket_accept($socket, -1);
            if ($connection === false) {
                continue;
            }

            stream_set_timeout($connection, self::READ_TIMEOUT_SECONDS);
            $request = $this->readRequest($connection);
            fwrite($connection, $this->respond($request));
            fclose($connection);
        }

        return 0;
    }

    /**
     * @return array{0: string, 1: string} head, body
     */
    private function split(string $raw): array
    {
        $separator = str_contains($raw, "\r\n\r\n") ? "\r\n\r\n" : "\n\n";
        $parts = explode($separator, $raw, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    /**
     * @param resource $connection
     */
    private function readRequest($connection): string
    {
        $request = '';
        $contentLength = 0;

        while (($line = fgets($connection)) !== false) {
            $request .= $line;

            if (preg_match('/^content-length:\s*(\d+)/i', $line, $matches) === 1) {
                $contentLength = (int) $matches[1];
            }

            if ($line === "\r\n" || $line === "\n") {
                $toRead = min($contentLength, self::MAX_BODY_BYTES);
                if ($toRead > 0) {
                    $request .= (string) stream_get_contents($connection, $toRead);
                }

                break;
            }
        }

        return $request;
    }

    private function httpResponse(int $status, string $reason, string $body): string
    {
        $headers = [
            \sprintf('HTTP/1.1 %d %s', $status, $reason),
            'Content-Type: application/json',
            'Content-Length: ' . \strlen($body),
            'Connection: close',
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }
}

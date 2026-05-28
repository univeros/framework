<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Server;

use Altair\Mcp\Contracts\ToolResolverInterface;
use Altair\Mcp\Exception\GuardrailException;
use Altair\Mcp\Protocol\ErrorCode;
use Altair\Mcp\Protocol\ErrorResponse;
use Altair\Mcp\Protocol\Request;
use Altair\Mcp\Protocol\Response;
use Altair\Mcp\Schema\SchemaValidator;
use Altair\Mcp\Tool\ToolRegistry;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

use stdClass;
use Throwable;

/**
 * The MCP server's pure message brain: it turns one inbound JSON-RPC message
 * into one outbound message (or null for notifications), with no knowledge of
 * the transport carrying those bytes.
 *
 * Protocol-level problems (bad JSON, unknown method, malformed params) become
 * JSON-RPC error responses. A tool that throws — including a guardrail
 * violation — becomes a *successful* `tools/call` result with `isError: true`,
 * per the MCP convention that tool failures are data the model can react to,
 * not transport errors.
 */
final readonly class Server
{
    public function __construct(
        private ToolRegistry $registry,
        private ToolResolverInterface $resolver,
        private SchemaValidator $validator,
        private ServerInfo $info = new ServerInfo(),
    ) {}

    /**
     * Handle a raw JSON-RPC message; return the raw reply, or null when the
     * message is a notification that warrants no response.
     */
    public function handle(string $raw): ?string
    {
        $decoded = json_decode($raw, true);
        if (!\is_array($decoded)) {
            return $this->encode(ErrorResponse::from(null, ErrorCode::ParseError, 'Parse error.')->toArray());
        }

        if (array_is_list($decoded)) {
            return $this->encode(ErrorResponse::from(null, ErrorCode::InvalidRequest, 'Batch requests are not supported.')->toArray());
        }

        $id = $decoded['id'] ?? null;
        if (!\is_int($id) && !\is_string($id)) {
            $id = null;
        }

        $method = $decoded['method'] ?? null;
        if (!\is_string($method)) {
            return $this->encode(ErrorResponse::from($id, ErrorCode::InvalidRequest, 'Invalid request: "method" must be a string.')->toArray());
        }

        $params = $decoded['params'] ?? [];
        if (!\is_array($params)) {
            $params = [];
        }

        $reply = $this->dispatch(new Request($method, $params, $id));

        return $reply === null ? null : $this->encode($reply);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function dispatch(Request $request): ?array
    {
        return match ($request->method) {
            'initialize' => (new Response($request->id, $this->initialize($request)))->toArray(),
            'ping' => (new Response($request->id, new stdClass()))->toArray(),
            'tools/list' => (new Response($request->id, ['tools' => $this->registry->listing()]))->toArray(),
            'tools/call' => $this->callTool($request),
            'notifications/initialized', 'notifications/cancelled' => null,
            default => $this->unknownMethod($request),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function initialize(Request $request): array
    {
        $requested = $request->param('protocolVersion');

        return [
            'protocolVersion' => $this->info->negotiateProtocol(\is_string($requested) ? $requested : null),
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => $this->info->name, 'version' => $this->info->version],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function unknownMethod(Request $request): ?array
    {
        if ($request->isNotification()) {
            return null;
        }

        return ErrorResponse::from(
            $request->id,
            ErrorCode::MethodNotFound,
            \sprintf("Method '%s' not found.", $request->method),
        )->toArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function callTool(Request $request): ?array
    {
        if ($request->isNotification()) {
            return null;
        }

        $name = $request->param('name');
        if (!\is_string($name)) {
            return ErrorResponse::from($request->id, ErrorCode::InvalidParams, "tools/call requires a string 'name'.")->toArray();
        }

        if (!$this->registry->has($name)) {
            return ErrorResponse::from($request->id, ErrorCode::MethodNotFound, \sprintf("Unknown tool '%s'.", $name))->toArray();
        }

        $arguments = $request->param('arguments', []);
        if (!\is_array($arguments)) {
            $arguments = [];
        }

        /** @var array<string, mixed> $arguments */
        $descriptor = $this->registry->get($name);

        $validation = $this->validator->validate($arguments, $descriptor->inputSchema);
        if (!$validation->valid) {
            return (new Response($request->id, $this->toolError($validation->message())))->toArray();
        }

        try {
            $output = $this->resolver->resolve($descriptor->className)->call($arguments);
        } catch (GuardrailException $guardrailException) {
            return (new Response($request->id, $this->toolError($guardrailException->getMessage())))->toArray();
        } catch (Throwable $throwable) {
            return (new Response($request->id, $this->toolError(\sprintf('%s failed: %s', $name, $throwable->getMessage()))))->toArray();
        }

        return (new Response($request->id, $this->toolSuccess($output)))->toArray();
    }

    /**
     * @param array<string, mixed> $output
     *
     * @return array<string, mixed>
     */
    private function toolSuccess(array $output): array
    {
        return [
            'content' => [['type' => 'text', 'text' => $this->encodeJson($output)]],
            'structuredContent' => $output === [] ? new stdClass() : $output,
            'isError' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toolError(string $message): array
    {
        return [
            'content' => [['type' => 'text', 'text' => $message]],
            'isError' => true,
        ];
    }

    /**
     * @param array<string, mixed> $message
     */
    private function encode(array $message): string
    {
        return json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{"jsonrpc":"2.0","id":null,"error":{"code":-32603,"message":"Internal error: response not encodable."}}';
    }

    /**
     * @param array<string, mixed> $output
     */
    private function encodeJson(array $output): string
    {
        return json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}

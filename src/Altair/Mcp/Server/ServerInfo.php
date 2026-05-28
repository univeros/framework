<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Server;

/**
 * Server identity plus MCP protocol-version negotiation.
 *
 * We support a small range of dated protocol revisions. During `initialize` we
 * echo the client's requested version when we know it, otherwise we answer with
 * our newest supported revision and let the client decide.
 */
final readonly class ServerInfo
{
    public const string DEFAULT_NAME = 'univeros-altair';

    public const string DEFAULT_VERSION = '0.1.0';

    /**
     * Newest first.
     *
     * @var list<string>
     */
    public const array SUPPORTED_PROTOCOLS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    public function __construct(
        public string $name = self::DEFAULT_NAME,
        public string $version = self::DEFAULT_VERSION,
    ) {}

    public function negotiateProtocol(?string $requested): string
    {
        if ($requested !== null && \in_array($requested, self::SUPPORTED_PROTOCOLS, true)) {
            return $requested;
        }

        return self::SUPPORTED_PROTOCOLS[0];
    }
}

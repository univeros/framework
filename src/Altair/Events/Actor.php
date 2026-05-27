<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events;

/**
 * Who triggered the event.
 *
 * `cli`    - bin/altair invocation by a human or agent shell
 * `mcp`    - an MCP client (e.g. claude-desktop) invoked a framework tool
 * `worker` - a long-running worker process (Messenger consumer, etc.)
 * `script` - a one-off PHP script (bootstrap, fixture loader, custom job)
 */
enum Actor: string
{
    case Cli = 'cli';
    case Mcp = 'mcp';
    case Worker = 'worker';
    case Script = 'script';
}

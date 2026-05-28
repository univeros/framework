<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Courier\Support;

use Altair\Courier\Contracts\CommandInterface;
use Altair\Structure\Map;

/**
 * @extends Map<string, class-string<CommandInterface>|CommandInterface>
 */
class MessageCommandMap extends Map {}

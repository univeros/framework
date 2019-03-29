<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Http\Contracts\FormatNegotiatorInterface;
use Altair\Http\Support\FormatNegotiator;

class FormatNegotiatorMiddlewareConfiguration implements ConfigurationInterface
{
    public function apply(Container $container)
    {
        $container
            ->alias(FormatNegotiatorInterface::class, FormatNegotiator::class);
    }
}

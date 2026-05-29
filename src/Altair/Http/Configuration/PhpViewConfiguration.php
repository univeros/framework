<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Altair\Http\Formatter\PhpViewFormatter;
use Altair\Http\Responder\FormattedResponder;
use Override;

class PhpViewConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    /**
     * @inheritDoc
     */
    #[Override]
    public function apply(Container $container): void
    {
        $container->bind(PhpViewFormatter::class)->withParameters([
            'templatePath' => $this->env->get('HTTP_PHP_VIEW_TEMPLATE_PATH'),
            'layout' => $this->env->get('HTTP_PHP_VIEW_LAYOUT'),
        ]);
        $container->extend(
            FormattedResponder::class,
            static fn(object $responder): object => $responder instanceof FormattedResponder
                ? $responder->withFormatter(PhpViewFormatter::class, 1.0)
                : $responder,
        );
    }
}

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
use Neomerx\Cors\Analyzer;
use Neomerx\Cors\Contracts\AnalysisStrategyInterface;
use Neomerx\Cors\Contracts\AnalyzerInterface;
use Neomerx\Cors\Contracts\Factory\FactoryInterface;
use Neomerx\Cors\Factory\Factory;
use Neomerx\Cors\Strategies\Settings;

class CorsMiddlewareConfiguration implements ConfigurationInterface
{
    /**
     * @inheritDoc
     */
    public function apply(Container $container): void
    {
        $container
            ->alias(AnalysisStrategyInterface::class, Settings::class) /** Settings class must be defined by user */
            ->alias(FactoryInterface::class, Factory::class)
            ->alias(AnalyzerInterface::class, Analyzer::class);
    }
}

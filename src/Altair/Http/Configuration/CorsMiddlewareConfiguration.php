<?php
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
     * @inheritdoc
     */
    public function apply(Container $container)
    {
        $container
            ->alias(AnalysisStrategyInterface::class, Settings::class) /** Settings class must be defined by user */
            ->alias(FactoryInterface::class, Factory::class)
            ->alias(AnalyzerInterface::class, Analyzer::class);
    }
}

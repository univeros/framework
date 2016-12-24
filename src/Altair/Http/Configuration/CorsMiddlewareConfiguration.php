<?php
namespace Altair\Http\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Neomerx\Cors\Contracts\AnalysisStrategyInterface;
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
            ->alias(AnalysisStrategyInterface::class, Settings::class)
            ->alias(FactoryInterface::class, Factory::class);
    }
}

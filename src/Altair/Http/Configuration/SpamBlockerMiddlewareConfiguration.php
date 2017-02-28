<?php
namespace Altair\Http\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Altair\Container\Definition;
use Altair\Http\Middleware\SpamBlockerMiddleware;

class SpamBlockerMiddlewareConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    /**
     * @inheritdoc
     */
    public function apply(Container $container)
    {
        // There are a couple of great sources to get a good lit of domain spammers.
        // I highly recommend https://github.com/desbma/referer-spam-domains-blacklist which contains a set of tools
        // to keep updated your blacklisted domains and also has a more updated list than the original repository it
        // was forked from (https://github.com/piwik/referrer-spam-blacklist)
        $definition = new Definition([
            ':path' => $this->env->get('HTTP_SPAMMERS_FILE_PATH')
        ]);

        $container
            ->define(SpamBlockerMiddleware::class, $definition);
    }
}

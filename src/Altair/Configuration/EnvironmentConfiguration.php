<?php
namespace Altair\Configuration;


use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Exception\InvalidArgumentException;
use Altair\Configuration\Support\Env;
use Altair\Container\Container;
use Altair\Container\Definition;
use Dotenv\Loader;

class EnvironmentConfiguration implements ConfigurationInterface
{
    protected $filePath;
    protected $immutable;

    /**
     * EnvironmentConfiguration constructor.
     *
     * @param string $filePath
     * @param bool $immutable don't override already existing environment variables if set to true
     */
    public function __construct(string $filePath, bool $immutable = true)
    {

        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException("Invalid environment file path: '$filePath'");
        }
        $this->filePath = $filePath;
        $this->immutable = $immutable;
    }

    /**
     * @inheritdoc
     */
    public function apply(Container $container)
    {
        $container
            ->share(Env::class)
            ->define(
                Loader::class,
                new Definition(
                    [
                        ':filePath' => $this->filePath,
                        ':immutable' => $this->immutable
                    ]
                )
            )
            ->prepare(
                Env::class,
                function (Env $env, Container $container) {

                    // ensure Loader loads environment file prior using Env::class
                    $loader = $container->make(Loader::class);
                    $loader->load();
                }
            );

    }

}

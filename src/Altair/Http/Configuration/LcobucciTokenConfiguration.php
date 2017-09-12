<?php

namespace Altair\Http\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Altair\Http\Contracts\TokenConfigurationInterface;
use Altair\Http\Support\TokenConfiguration;
use Lcobucci\Jose\Parsing\Decoder;
use Lcobucci\Jose\Parsing\Encoder;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Validator;

class LcobucciTokenConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    /**
     * @inheritdoc
     */
    public function apply(Container $container)
    {
        $tokenGeneratorConfigurationFactory = function () {
            return new TokenConfiguration(
                $this->env->get('TOKEN_PUBLIC_KEY', 'YOU_SHOULD_CHANGE_THIS'),
                (int) $this->env->get('TOKEN_TTL', ini_get('session.gc_maxlifetime')),
                new Sha256(),
                null,
                $this->env->get('TOKEN_PRIVATE_KEY')
            );
        };

        $container
            ->alias(TokenConfigurationInterface::class, TokenConfiguration::class)
            ->alias(Validator::class, \Lcobucci\JWT\Validation\Validator::class)
            ->alias(Encoder::class, \Lcobucci\Jose\Parsing\Parser::class)
            ->alias(Decoder::class, \Lcobucci\Jose\Parsing\Parser::class)
            ->alias(Builder::class, \Lcobucci\JWT\Token\Builder::class)
            ->alias(Parser::class, \Lcobucci\JWT\Token\Parser::class)
            ->delegate(TokenConfiguration::class, $tokenGeneratorConfigurationFactory);
    }
}

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
use Altair\Http\Contracts\TokenConfigurationInterface;
use Altair\Http\Exception\RuntimeException;
use Altair\Http\Support\TokenConfiguration;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Override;

/**
 * Wires the framework {@see TokenConfigurationInterface} from environment variables.
 *
 * The lcobucci/jwt v5 builder, parser and validator are derived on demand from this
 * configuration inside {@see \Altair\Http\Jwt\LcobucciTokenGenerator} and
 * {@see \Altair\Http\Jwt\LcobucciTokenParser}, so no library primitives are bound here.
 */
class LcobucciTokenConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    /**
     * Legacy placeholder default; rejected at runtime so a misconfigured deployment fails fast
     * instead of silently issuing tokens that can never be verified.
     */
    private const string UNCONFIGURED_KEY = 'YOU_SHOULD_CHANGE_THIS';

    /**
     * @inheritDoc
     */
    #[Override]
    public function apply(Container $container): void
    {
        $tokenConfigurationFactory = function (): TokenConfiguration {
            $publicKey = (string) $this->env->get('TOKEN_PUBLIC_KEY', '');

            if ($publicKey === '' || $publicKey === self::UNCONFIGURED_KEY) {
                throw new RuntimeException(
                    'TOKEN_PUBLIC_KEY must be set to a valid token verification key.'
                );
            }

            $issuer = (string) $this->env->get('TOKEN_ISSUER', '');

            if ($issuer === '') {
                throw new RuntimeException(
                    'TOKEN_ISSUER must be set to a stable issuer identifier for the tokens this service mints.'
                );
            }

            $audience = $this->env->get('TOKEN_AUDIENCE');

            return new TokenConfiguration(
                $publicKey,
                (int) $this->env->get('TOKEN_TTL', \ini_get('session.gc_maxlifetime')),
                new Sha256(),
                $issuer,
                null,
                $this->env->get('TOKEN_PRIVATE_KEY'),
                $audience === null ? null : (string) $audience
            );
        };

        $container
            ->alias(TokenConfigurationInterface::class, TokenConfiguration::class)
            ->delegate(TokenConfiguration::class, $tokenConfigurationFactory);
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Jwt;

use Altair\Http\Contracts\TokenConfigurationInterface;
use Altair\Http\Contracts\TokenGeneratorInterface;
use Altair\Http\Exception\InvalidTokenException;
use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Override;

/**
 * Generates signed JWTs using lcobucci/jwt v5.
 *
 * Each call mints a fresh, immutable builder from a {@see Configuration} derived from the
 * framework {@see TokenConfigurationInterface}. Asymmetric signing is assumed (RSA/ECDSA),
 * so a private key must be configured. The `iss` claim is the configured stable issuer and
 * the `aud` claim is added when an audience is configured.
 */
class LcobucciTokenGenerator implements TokenGeneratorInterface
{
    public function __construct(protected TokenConfigurationInterface $config) {}

    /**
     * @inheritDoc
     *
     * @param array<string, mixed> $claims
     *
     * @throws InvalidTokenException when no private key is configured for signing
     */
    #[Override]
    public function generate(array $claims = []): string
    {
        $privateKey = $this->config->getPrivateKey();

        if ($privateKey === null || $privateKey === '') {
            throw new InvalidTokenException('A private key is required to generate a token.');
        }

        $configuration = Configuration::forAsymmetricSigner(
            $this->config->getSigner(),
            InMemory::plainText($privateKey),
            InMemory::plainText($this->config->getPublicKey()),
        );

        $builder = $configuration->builder()
            ->issuedBy($this->config->getIssuer())
            ->issuedAt((new DateTimeImmutable())->setTimestamp($this->config->getTimestamp()))
            ->expiresAt((new DateTimeImmutable())->setTimestamp($this->config->getExpirationTimestamp()));

        $audience = $this->config->getAudience();

        if ($audience !== null && $audience !== '') {
            $builder = $builder->permittedFor($audience);
        }

        foreach ($claims as $name => $value) {
            if ($name === '') {
                continue;
            }

            // Builder is immutable in v5: each withClaim() returns a new instance.
            $builder = $builder->withClaim($name, $value);
        }

        return $builder
            ->getToken($configuration->signer(), $configuration->signingKey())
            ->toString();
    }
}

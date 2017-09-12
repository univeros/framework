<?php

namespace Altair\Http\Jwt;

use Altair\Http\Contracts\TokenConfigurationInterface;
use Altair\Http\Contracts\TokenGeneratorInterface;
use DateTimeImmutable;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Key;
use Psr\Http\Message\ServerRequestInterface;

class LcobucciTokenGenerator implements TokenGeneratorInterface
{
    protected $request;
    protected $builder;
    protected $config;

    /**
     * LcobucciTokenGenerator constructor.
     *
     * @param ServerRequestInterface $request
     * @param Builder $builder
     * @param TokenConfigurationInterface $config
     */
    public function __construct(
        ServerRequestInterface $request,
        Builder $builder,
        TokenConfigurationInterface $config
    ) {
        $this->request = $request;
        $this->builder = $builder;
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function generate(array $claims = []): string
    {
        $issuer = (string)$this->request->getUri();
        $issued_at = (new DateTimeImmutable())->setTimestamp($this->config->getTimestamp());
        $expiration = (new DateTimeImmutable())->setTimestamp($this->config->getExpirationTimestamp());
        // Assumed RSA or ECDSA signatures (highly recommended)
        // Signatures are based on public and private keys so you have to generate using the private key and verify
        // using the public key
        $key = new Key($this->config->getPrivateKey());
        foreach ($claims as $name => $value) {
            $this->builder->withClaim($name, $value);
        }

        return (string)$this
            ->builder
            ->issuedBy($issuer)
            ->issuedAt($issued_at)
            ->expiresAt($expiration)
            ->getToken($this->config->getSigner(), $key);
    }
}

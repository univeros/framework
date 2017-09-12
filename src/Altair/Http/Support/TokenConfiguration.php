<?php

namespace Altair\Http\Support;

use Altair\Http\Contracts\TokenConfigurationInterface;
use Lcobucci\JWT\Signer;

final class TokenConfiguration implements TokenConfigurationInterface
{
    private $publicKey;
    private $ttl;
    private $signer;
    private $timestamp;
    private $privateKey;

    /**
     * TokenGeneratorConfiguration constructor.
     *
     * @param string $publicKey
     * @param int $ttl
     * @param Signer $signer
     * @param int|null $timestamp
     * @param string|null $privateKey
     */
    public function __construct(
        string $publicKey,
        int $ttl,
        Signer $signer,
        int $timestamp = null,
        string $privateKey = null
    ) {
        $this->publicKey = $publicKey;
        $this->ttl = $ttl;
        $this->signer = $signer;
        $this->timestamp = $timestamp ?: time();
        $this->privateKey = $privateKey;
    }

    /**
     * @inheritdoc
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * @inheritdoc
     */
    public function getTtl(): int
    {
        return $this->ttl;
    }

    /**
     * @inheritdoc
     */
    public function getSigner(): Signer
    {
        return $this->signer;
    }

    /**
     * @inheritdoc
     */
    public function getTimestamp():int
    {
        return $this->timestamp;
    }

    /**
     * @inheritdoc
     */
    public function getPrivateKey(): ?string
    {
        return $this->privateKey;
    }

    /**
     * @inheritdoc
     */
    public function getExpirationTimestamp(): int
    {
        return $this->getTimestamp() + $this->getTtl();
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Support;

use Altair\Http\Contracts\TokenConfigurationInterface;
use Lcobucci\JWT\Signer;
use Override;

final readonly class TokenConfiguration implements TokenConfigurationInterface
{
    private int $timestamp;

    /**
     * TokenGeneratorConfiguration constructor.
     */
    public function __construct(
        private string $publicKey,
        private int $ttl,
        private Signer $signer,
        ?int $timestamp = null,
        private ?string $privateKey = null
    ) {
        $this->timestamp = $timestamp ?: time();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getTtl(): int
    {
        return $this->ttl;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getSigner(): Signer
    {
        return $this->signer;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getPrivateKey(): ?string
    {
        return $this->privateKey;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getExpirationTimestamp(): int
    {
        return $this->timestamp + $this->ttl;
    }
}

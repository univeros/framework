<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Signing;

use Altair\Webhooks\Contracts\SignerInterface;
use Altair\Webhooks\Exception\WebhookException;

/**
 * Maps a wire scheme name (e.g. 'hmac-sha256') to its signer implementation.
 * Used by the middleware + dispatcher to pick the signer the spec declared.
 */
final class SignerRegistry
{
    /** @var array<string, SignerInterface> */
    private array $signers = [];

    /**
     * @param iterable<SignerInterface> $signers
     */
    public function __construct(iterable $signers = [])
    {
        foreach ($signers as $signer) {
            $this->register($signer);
        }
    }

    /**
     * Build a registry with the always-available HMAC signers, plus Ed25519
     * when ext-sodium is loaded.
     */
    public static function default(): self
    {
        $signers = [new HmacSha256Signer(), new HmacSha512Signer()];
        if (\extension_loaded('sodium')) {
            $signers[] = new Ed25519Signer();
        }

        return new self($signers);
    }

    public function register(SignerInterface $signer): void
    {
        $this->signers[$signer->name()] = $signer;
    }

    public function has(string $name): bool
    {
        return isset($this->signers[$name]);
    }

    public function get(string $name): SignerInterface
    {
        return $this->signers[$name] ?? throw WebhookException::unknownSigner($name);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->signers);
    }
}

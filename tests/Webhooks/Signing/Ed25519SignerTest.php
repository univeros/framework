<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Signing;

use Altair\Webhooks\Signing\Ed25519Signer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ed25519Signer::class)]
#[RequiresPhpExtension('sodium')]
final class Ed25519SignerTest extends TestCase
{
    private const string PAYLOAD = '{"id":"evt_1","type":"order.created"}';

    public function testNameIsWireScheme(): void
    {
        self::assertSame('ed25519', (new Ed25519Signer())->name());
    }

    public function testSignThenVerifyRoundTrip(): void
    {
        $signer = new Ed25519Signer();
        [$secretKey, $publicKey] = $this->keypair();

        $signature = $signer->sign(self::PAYLOAD, $secretKey);

        self::assertTrue($signer->verify(self::PAYLOAD, $signature, $publicKey));
    }

    public function testVerifyRejectsTamperedPayload(): void
    {
        $signer = new Ed25519Signer();
        [$secretKey, $publicKey] = $this->keypair();
        $signature = $signer->sign(self::PAYLOAD, $secretKey);

        self::assertFalse($signer->verify(self::PAYLOAD . 'x', $signature, $publicKey));
    }

    public function testVerifyRejectsSignatureFromAnotherKey(): void
    {
        $signer = new Ed25519Signer();
        [$secretKey] = $this->keypair();
        [, $otherPublicKey] = $this->keypair();
        $signature = $signer->sign(self::PAYLOAD, $secretKey);

        self::assertFalse($signer->verify(self::PAYLOAD, $signature, $otherPublicKey));
    }

    public function testVerifyRejectsMalformedSignature(): void
    {
        $signer = new Ed25519Signer();
        [, $publicKey] = $this->keypair();

        self::assertFalse($signer->verify(self::PAYLOAD, 'not-hex!!', $publicKey));
        self::assertFalse($signer->verify(self::PAYLOAD, 'abcd', $publicKey));
    }

    /**
     * @return array{0: string, 1: string} hex-encoded [secretKey, publicKey]
     */
    private function keypair(): array
    {
        $keypair = sodium_crypto_sign_keypair();

        return [
            sodium_bin2hex(sodium_crypto_sign_secretkey($keypair)),
            sodium_bin2hex(sodium_crypto_sign_publickey($keypair)),
        ];
    }
}

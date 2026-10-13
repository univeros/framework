<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Signing;

use Altair\Webhooks\Signing\AbstractHmacSigner;
use Altair\Webhooks\Signing\HmacSha512Signer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HmacSha512Signer::class)]
#[CoversClass(AbstractHmacSigner::class)]
final class HmacSha512SignerTest extends TestCase
{
    private const string SECRET = 'whsec_test_secret';

    private const string PAYLOAD = '{"id":"evt_1"}';

    public function testNameIsWireScheme(): void
    {
        self::assertSame('hmac-sha512', (new HmacSha512Signer())->name());
    }

    public function testSignProducesHexEncodedMac(): void
    {
        $signer = new HmacSha512Signer();

        $signature = $signer->sign(self::PAYLOAD, self::SECRET);

        self::assertSame(hash_hmac('sha512', self::PAYLOAD, self::SECRET), $signature);
        self::assertSame(128, strlen($signature));
    }

    public function testVerifyRoundTrip(): void
    {
        $signer = new HmacSha512Signer();
        $signature = $signer->sign(self::PAYLOAD, self::SECRET);

        self::assertTrue($signer->verify(self::PAYLOAD, $signature, self::SECRET));
    }

    public function testVerifyRejectsCorruptedSignature(): void
    {
        $signer = new HmacSha512Signer();
        $signature = $signer->sign(self::PAYLOAD, self::SECRET);
        $corrupted = ($signature[0] === 'a' ? 'b' : 'a') . substr($signature, 1);

        self::assertFalse($signer->verify(self::PAYLOAD, $corrupted, self::SECRET));
    }

    public function testVerifyAcceptsItsOwnAlgorithmPrefix(): void
    {
        $signer = new HmacSha512Signer();
        $mac = $signer->sign(self::PAYLOAD, self::SECRET);

        self::assertTrue($signer->verify(self::PAYLOAD, 'sha512=' . $mac, self::SECRET));
        self::assertTrue($signer->verify(self::PAYLOAD, 'SHA512=' . $mac, self::SECRET));
    }

    public function testVerifyRejectsItsOwnPrefixWithAWrongMac(): void
    {
        $signer = new HmacSha512Signer();
        $mac = $signer->sign(self::PAYLOAD, self::SECRET);
        $wrong = ($mac[0] === 'a' ? 'b' : 'a') . substr($mac, 1);

        self::assertFalse($signer->verify(self::PAYLOAD, 'sha512=' . $wrong, self::SECRET));
    }

    public function testVerifyRejectsAPrefixNamingAnotherAlgorithm(): void
    {
        $signer = new HmacSha512Signer();
        $mac = $signer->sign(self::PAYLOAD, self::SECRET);

        self::assertFalse($signer->verify(self::PAYLOAD, 'sha256=' . $mac, self::SECRET));
        self::assertFalse($signer->verify(self::PAYLOAD, 'sha1=' . $mac, self::SECRET));
        self::assertFalse($signer->verify(self::PAYLOAD, 'sha512=', self::SECRET));
    }
}

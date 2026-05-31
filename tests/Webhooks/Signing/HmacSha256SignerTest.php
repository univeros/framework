<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Signing;

use Altair\Webhooks\Signing\HmacSha256Signer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HmacSha256Signer::class)]
#[CoversClass(\Altair\Webhooks\Signing\AbstractHmacSigner::class)]
final class HmacSha256SignerTest extends TestCase
{
    private const string SECRET = 'whsec_test_secret';
    private const string PAYLOAD = '{"id":"evt_1","type":"order.created"}';

    public function testNameIsWireScheme(): void
    {
        self::assertSame('hmac-sha256', (new HmacSha256Signer())->name());
    }

    public function testSignProducesHexEncodedMac(): void
    {
        $signer = new HmacSha256Signer();

        $signature = $signer->sign(self::PAYLOAD, self::SECRET);

        self::assertSame(hash_hmac('sha256', self::PAYLOAD, self::SECRET), $signature);
        self::assertSame(64, strlen($signature));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $signature);
    }

    public function testVerifyAcceptsAValidBareHexSignature(): void
    {
        $signer = new HmacSha256Signer();
        $signature = $signer->sign(self::PAYLOAD, self::SECRET);

        self::assertTrue($signer->verify(self::PAYLOAD, $signature, self::SECRET));
    }

    public function testVerifyRejectsACorruptedSignature(): void
    {
        $signer = new HmacSha256Signer();
        $signature = $signer->sign(self::PAYLOAD, self::SECRET);

        // Flip the first hex char — verify must reject rather than partial-match.
        $corrupted = ($signature[0] === 'a' ? 'b' : 'a') . substr($signature, 1);

        self::assertFalse($signer->verify(self::PAYLOAD, $corrupted, self::SECRET));
    }

    public function testVerifyRejectsTamperedPayload(): void
    {
        $signer = new HmacSha256Signer();
        $signature = $signer->sign(self::PAYLOAD, self::SECRET);

        self::assertFalse($signer->verify(self::PAYLOAD . 'x', $signature, self::SECRET));
    }

    public function testVerifyRejectsWrongSecret(): void
    {
        $signer = new HmacSha256Signer();
        $signature = $signer->sign(self::PAYLOAD, self::SECRET);

        self::assertFalse($signer->verify(self::PAYLOAD, $signature, 'wrong_secret'));
    }

    public function testVerifyRejectsEmptySignature(): void
    {
        $signer = new HmacSha256Signer();

        self::assertFalse($signer->verify(self::PAYLOAD, '', self::SECRET));
        self::assertFalse($signer->verify(self::PAYLOAD, '   ', self::SECRET));
    }

    public function testVerifyExtractsStripeStyleV1Component(): void
    {
        $signer = new HmacSha256Signer();
        $mac = $signer->sign(self::PAYLOAD, self::SECRET);

        $header = 't=1700000000,v1=' . $mac;

        self::assertTrue($signer->verify(self::PAYLOAD, $header, self::SECRET));
    }

    public function testVerifyRejectsStripeHeaderWithBadV1(): void
    {
        $signer = new HmacSha256Signer();

        $header = 't=1700000000,v1=' . str_repeat('0', 64);

        self::assertFalse($signer->verify(self::PAYLOAD, $header, self::SECRET));
    }
}

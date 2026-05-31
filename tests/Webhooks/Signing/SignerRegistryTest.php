<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Signing;

use Altair\Webhooks\Exception\WebhookException;
use Altair\Webhooks\Signing\HmacSha256Signer;
use Altair\Webhooks\Signing\HmacSha512Signer;
use Altair\Webhooks\Signing\SignerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SignerRegistry::class)]
final class SignerRegistryTest extends TestCase
{
    public function testResolvesRegisteredSignersByWireName(): void
    {
        $registry = new SignerRegistry([new HmacSha256Signer(), new HmacSha512Signer()]);

        self::assertInstanceOf(HmacSha256Signer::class, $registry->get('hmac-sha256'));
        self::assertInstanceOf(HmacSha512Signer::class, $registry->get('hmac-sha512'));
    }

    public function testHasReportsMembership(): void
    {
        $registry = new SignerRegistry([new HmacSha256Signer()]);

        self::assertTrue($registry->has('hmac-sha256'));
        self::assertFalse($registry->has('ed25519'));
    }

    public function testGetThrowsForUnknownSigner(): void
    {
        $registry = new SignerRegistry([new HmacSha256Signer()]);

        $this->expectException(WebhookException::class);
        $this->expectExceptionMessage('Unknown webhook signer "nope".');

        $registry->get('nope');
    }

    public function testDefaultRegistryIncludesHmacSigners(): void
    {
        $registry = SignerRegistry::default();

        self::assertTrue($registry->has('hmac-sha256'));
        self::assertTrue($registry->has('hmac-sha512'));
        self::assertContains('hmac-sha256', $registry->names());
    }

    public function testRegisterOverwritesByName(): void
    {
        $registry = new SignerRegistry();
        $registry->register(new HmacSha256Signer());

        self::assertSame(['hmac-sha256'], $registry->names());
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Http\Jwt;

use Altair\Http\Exception\InvalidTokenException;
use Altair\Http\Jwt\LcobucciTokenGenerator;
use Altair\Http\Support\TokenConfiguration;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\UnencryptedToken;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LcobucciTokenGenerator::class)]
final class LcobucciTokenGeneratorTest extends TestCase
{
    private const string ISSUER = 'https://api.example.test';

    private const int ISSUED_AT = 1_700_000_000;

    private const int TTL = 3600;

    private static string $privateKey;

    private static string $publicKey;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        if (!\extension_loaded('openssl')) {
            self::markTestSkipped('ext-openssl is not loaded.');
        }

        [self::$privateKey, self::$publicKey] = JwtTestKeys::rsaKeyPair();
    }

    public function testGenerateProducesParseableTokenWithRegisteredAndCustomClaims(): void
    {
        $config = new TokenConfiguration(self::$publicKey, self::TTL, new Sha256(), self::ISSUER, self::ISSUED_AT, self::$privateKey);

        $jwt = (new LcobucciTokenGenerator($config))->generate(['uid' => 42, 'role' => 'admin']);

        self::assertCount(3, explode('.', $jwt), 'A JWT is a three-segment dot-delimited string.');

        $parsed = $this->verificationConfiguration()->parser()->parse($jwt);
        self::assertInstanceOf(UnencryptedToken::class, $parsed);

        $claims = $parsed->claims();
        self::assertSame(self::ISSUER, $claims->get('iss'));
        self::assertSame(42, $claims->get('uid'));
        self::assertSame('admin', $claims->get('role'));
        self::assertSame(self::ISSUED_AT, $claims->get('iat')->getTimestamp());
        self::assertSame(self::ISSUED_AT + self::TTL, $claims->get('exp')->getTimestamp());
    }

    public function testGenerateIncludesAudienceWhenConfigured(): void
    {
        $config = new TokenConfiguration(
            self::$publicKey,
            self::TTL,
            new Sha256(),
            self::ISSUER,
            self::ISSUED_AT,
            self::$privateKey,
            'https://client.example.test'
        );

        $jwt = (new LcobucciTokenGenerator($config))->generate();

        $parsed = $this->verificationConfiguration()->parser()->parse($jwt);
        self::assertInstanceOf(UnencryptedToken::class, $parsed);
        self::assertContains('https://client.example.test', $parsed->claims()->get('aud'));
    }

    public function testGenerateThrowsWhenPrivateKeyIsMissing(): void
    {
        $config = new TokenConfiguration(self::$publicKey, self::TTL, new Sha256(), self::ISSUER, self::ISSUED_AT);

        $this->expectException(InvalidTokenException::class);

        (new LcobucciTokenGenerator($config))->generate();
    }

    private function verificationConfiguration(): Configuration
    {
        return Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText(self::$privateKey),
            InMemory::plainText(self::$publicKey),
        );
    }
}

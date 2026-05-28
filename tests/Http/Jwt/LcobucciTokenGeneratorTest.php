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
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Uri;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\UnencryptedToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LcobucciTokenGenerator::class)]
final class LcobucciTokenGeneratorTest extends TestCase
{
    private static string $privateKey;

    private static string $publicKey;

    public static function setUpBeforeClass(): void
    {
        if (!\extension_loaded('openssl')) {
            self::markTestSkipped('ext-openssl is not loaded.');
        }

        [self::$privateKey, self::$publicKey] = JwtTestKeys::rsaKeyPair();
    }

    public function testGenerateProducesParseableTokenWithRegisteredAndCustomClaims(): void
    {
        $issuedAt = 1_700_000_000;
        $ttl = 3600;
        $request = (new ServerRequest())->withUri(new Uri('https://api.example.test/login'));
        $config = new TokenConfiguration(self::$publicKey, $ttl, new Sha256(), $issuedAt, self::$privateKey);

        $jwt = (new LcobucciTokenGenerator($request, $config))->generate(['uid' => 42, 'role' => 'admin']);

        self::assertCount(3, explode('.', $jwt), 'A JWT is a three-segment dot-delimited string.');

        $parsed = $this->verificationConfiguration()->parser()->parse($jwt);
        self::assertInstanceOf(UnencryptedToken::class, $parsed);

        $claims = $parsed->claims();
        self::assertSame('https://api.example.test/login', $claims->get('iss'));
        self::assertSame(42, $claims->get('uid'));
        self::assertSame('admin', $claims->get('role'));
        self::assertSame($issuedAt, $claims->get('iat')->getTimestamp());
        self::assertSame($issuedAt + $ttl, $claims->get('exp')->getTimestamp());
    }

    public function testGenerateThrowsWhenPrivateKeyIsMissing(): void
    {
        $request = (new ServerRequest())->withUri(new Uri('https://api.example.test/login'));
        $config = new TokenConfiguration(self::$publicKey, 3600, new Sha256(), 1_700_000_000, null);

        $this->expectException(InvalidTokenException::class);

        (new LcobucciTokenGenerator($request, $config))->generate();
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

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
use Altair\Http\Jwt\LcobucciTokenParser;
use Altair\Http\Support\TokenConfiguration;
use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LcobucciTokenParser::class)]
final class LcobucciTokenParserTest extends TestCase
{
    private const string ISSUER = 'https://api.example.test';

    private const string AUDIENCE = 'https://client.example.test';

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

    public function testParseReturnsTokenWithClaimsForValidToken(): void
    {
        $jwt = $this->mintToken(self::ISSUER, self::ISSUED_AT + self::TTL, ['uid' => 7]);
        $parser = $this->parser(FrozenClock::at(self::ISSUED_AT + 10));

        $token = $parser->parse($jwt);

        self::assertSame($jwt, $token->getToken());
        self::assertSame(7, $token->getMetadata('uid'));
    }

    public function testParseRejectsMalformedToken(): void
    {
        $this->expectException(InvalidTokenException::class);

        $this->parser(FrozenClock::at(self::ISSUED_AT))->parse('this-is-not-a-jwt');
    }

    public function testParseRejectsTamperedSignature(): void
    {
        $jwt = $this->mintToken(self::ISSUER, self::ISSUED_AT + self::TTL, ['uid' => 7]);
        $tampered = substr($jwt, 0, -2) . (str_ends_with($jwt, 'AA') ? 'BB' : 'AA');

        $this->expectException(InvalidTokenException::class);

        $this->parser(FrozenClock::at(self::ISSUED_AT + 10))->parse($tampered);
    }

    public function testParseRejectsExpiredToken(): void
    {
        $jwt = $this->mintToken(self::ISSUER, self::ISSUED_AT + self::TTL, ['uid' => 7]);
        $parser = $this->parser(FrozenClock::at(self::ISSUED_AT + self::TTL + 1));

        $this->expectException(InvalidTokenException::class);

        $parser->parse($jwt);
    }

    public function testParseRejectsWrongIssuer(): void
    {
        $jwt = $this->mintToken('https://evil.example.test', self::ISSUED_AT + self::TTL, ['uid' => 7]);
        $parser = $this->parser(FrozenClock::at(self::ISSUED_AT + 10));

        $this->expectException(InvalidTokenException::class);

        $parser->parse($jwt);
    }

    public function testParseRejectsTokenSignedWithForeignKey(): void
    {
        [$foreignPrivate, $foreignPublic] = JwtTestKeys::rsaKeyPair();
        $foreign = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText($foreignPrivate),
            InMemory::plainText($foreignPublic),
        );
        $jwt = $foreign->builder()
            ->issuedBy(self::ISSUER)
            ->issuedAt((new DateTimeImmutable())->setTimestamp(self::ISSUED_AT))
            ->expiresAt((new DateTimeImmutable())->setTimestamp(self::ISSUED_AT + self::TTL))
            ->getToken($foreign->signer(), $foreign->signingKey())
            ->toString();

        $this->expectException(InvalidTokenException::class);

        $this->parser(FrozenClock::at(self::ISSUED_AT + 10))->parse($jwt);
    }

    public function testParseAcceptsTokenWithMatchingAudience(): void
    {
        $jwt = $this->mintToken(self::ISSUER, self::ISSUED_AT + self::TTL, ['uid' => 7], self::AUDIENCE);
        $parser = $this->parser(FrozenClock::at(self::ISSUED_AT + 10), self::AUDIENCE);

        $token = $parser->parse($jwt);

        self::assertSame(7, $token->getMetadata('uid'));
    }

    public function testParseRejectsTokenWithMissingAudienceWhenAudienceRequired(): void
    {
        $jwt = $this->mintToken(self::ISSUER, self::ISSUED_AT + self::TTL, ['uid' => 7]);
        $parser = $this->parser(FrozenClock::at(self::ISSUED_AT + 10), self::AUDIENCE);

        $this->expectException(InvalidTokenException::class);

        $parser->parse($jwt);
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function mintToken(string $issuer, int $expiresAt, array $claims, ?string $audience = null): string
    {
        $configuration = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText(self::$privateKey),
            InMemory::plainText(self::$publicKey),
        );

        $builder = $configuration->builder()
            ->issuedBy($issuer)
            ->issuedAt((new DateTimeImmutable())->setTimestamp(self::ISSUED_AT))
            ->expiresAt((new DateTimeImmutable())->setTimestamp($expiresAt));

        if ($audience !== null) {
            $builder = $builder->permittedFor($audience);
        }

        foreach ($claims as $name => $value) {
            $builder = $builder->withClaim($name, $value);
        }

        return $builder->getToken($configuration->signer(), $configuration->signingKey())->toString();
    }

    private function parser(FrozenClock $clock, ?string $audience = null): LcobucciTokenParser
    {
        $config = new TokenConfiguration(
            self::$publicKey,
            self::TTL,
            new Sha256(),
            self::ISSUER,
            self::ISSUED_AT,
            self::$privateKey,
            $audience
        );

        return new LcobucciTokenParser($config, $clock);
    }
}

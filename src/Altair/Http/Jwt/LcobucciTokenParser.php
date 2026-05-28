<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Jwt;

use Altair\Http\Contracts\TokenConfigurationInterface;
use Altair\Http\Contracts\TokenInterface;
use Altair\Http\Contracts\TokenParserInterface;
use Altair\Http\Exception\InvalidTokenException;
use Altair\Http\Support\Token;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Override;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Parses and fully validates JWTs using lcobucci/jwt v5.
 *
 * Validation asserts the signature, the issuer (the requesting URI) and the token's
 * time window (expiry / not-before / issued-at) against an injectable PSR-20 clock.
 *
 * The verification algorithm is fixed to the framework-configured signer; lcobucci's
 * SignedWith rejects any token whose `alg` header does not match that signer, so
 * algorithm-confusion and `alg=none` tokens are rejected before signature verification.
 */
class LcobucciTokenParser implements TokenParserInterface
{
    private readonly ClockInterface $clock;

    public function __construct(
        protected ServerRequestInterface $request,
        protected TokenConfigurationInterface $config,
        ?ClockInterface $clock = null
    ) {
        $this->clock = $clock ?? new SystemClock();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function parse(string $token): TokenInterface
    {
        $configuration = $this->buildConfiguration();
        $parsed = $this->parseToken($configuration, $token);

        try {
            $configuration->validator()->assert(
                $parsed,
                new SignedWith($this->config->getSigner(), $configuration->verificationKey()),
                new IssuedBy((string) $this->request->getUri()),
                new LooseValidAt($this->clock),
            );
        } catch (RequiredConstraintsViolated $requiredConstraintsViolated) {
            throw new InvalidTokenException($requiredConstraintsViolated->getMessage(), $requiredConstraintsViolated);
        }

        return new Token($token, $parsed->claims()->all());
    }

    private function buildConfiguration(): Configuration
    {
        // The signing (private) key is never used when parsing; the public key satisfies
        // the asymmetric factory and is the key actually used for signature verification.
        $verificationKey = InMemory::plainText($this->config->getPublicKey());

        return Configuration::forAsymmetricSigner($this->config->getSigner(), $verificationKey, $verificationKey);
    }

    /**
     * @throws InvalidTokenException
     */
    private function parseToken(Configuration $configuration, string $token): UnencryptedToken
    {
        // The raw token is intentionally omitted from the message to avoid leaking it into
        // logs/responses and to prevent log injection via crafted token bytes.
        try {
            $parsed = $configuration->parser()->parse($token);
        } catch (CannotDecodeContent | InvalidTokenStructure | UnsupportedHeaderFound $exception) {
            throw new InvalidTokenException('Could not parse the authorization token.', $exception);
        }

        if (!$parsed instanceof UnencryptedToken) {
            throw new InvalidTokenException('Could not parse the authorization token.');
        }

        return $parsed;
    }
}
